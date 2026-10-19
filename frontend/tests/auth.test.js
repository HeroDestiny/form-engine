import { beforeEach, test } from 'node:test'
import assert from 'node:assert/strict'
import { apiRequest, exportSubmissionsCsv, login } from '../src/api.js'
import { canAdminister, canManageForms, canManageTenants, restoreSession, session, signIn, signOut } from '../src/auth/session.js'
import { clearAuthToken, getAuthToken, setAuthToken, syncAuthToken } from '../src/auth/token.js'
import { adminRoles, managementRoles, safeReturnPath } from '../src/auth/permissions.js'
import { guardRoute } from '../src/router/guards.js'

const values = new Map()
globalThis.window = {
  localStorage: {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  },
}
const json = (data, status = 200) => new Response(JSON.stringify(data), {
  status, headers: { 'Content-Type': 'application/json' },
})
const identity = (role = 'user') => ({ data: { user: { id: 1, name: 'Teste', role }, tenant: { id: 1, name: 'Demo' } } })
const route = (overrides = {}) => ({ name: 'home', fullPath: '/', query: {}, meta: {}, ...overrides })

beforeEach(() => {
  setAuthToken('reset')
  clearAuthToken()
  globalThis.fetch = async () => { throw new Error('Unexpected network request') }
})

test('anonymous navigation preserves the requested page for login', async () => {
  assert.deepEqual(await guardRoute(route({ fullPath: '/submissions/42?tab=values' })), {
    name: 'login', query: { redirect: '/submissions/42?tab=values' },
  })
  assert.equal(await guardRoute(route({ name: 'login', meta: { public: true } })), true)
})

for (const [role, manages, administers, tenants] of [
  ['user', false, false, false], ['manager', true, false, false],
  ['admin', true, true, false], ['admin-sistema', true, true, true],
]) {
  test(`${role}: session permissions and direct URL restrictions`, async () => {
    setAuthToken(role)
    globalThis.fetch = async () => json(identity(role))
    assert.equal(await guardRoute(route()), true)
    assert.equal(canManageForms.value, manages)
    assert.equal(canAdminister.value, administers)
    assert.equal(canManageTenants.value, tenants)
    for (const [roles, allowed] of [[managementRoles, manages], [adminRoles, administers], [['admin-sistema'], tenants]]) {
      assert.deepEqual(await guardRoute(route({ meta: { roles } })), allowed ? true : { name: 'forbidden' })
    }
  })
}

test('restoration shares concurrent requests and caches the verified identity', async () => {
  setAuthToken('valid')
  let calls = 0
  globalThis.fetch = async (_url, options) => {
    calls++
    assert.equal(options.headers.get('Accept'), 'application/json')
    assert.equal(options.headers.get('Authorization'), 'Bearer valid')
    return json(identity())
  }
  await Promise.all([restoreSession(), restoreSession(), restoreSession()])
  await restoreSession()
  assert.equal(calls, 1)
  assert.equal(session.tenant.id, 1)
})

test('expired session clears token and identity and returns to login', async () => {
  setAuthToken('expired')
  globalThis.fetch = async () => json({ message: 'Unauthenticated.' }, 401)
  assert.deepEqual(await guardRoute(route({ fullPath: '/submissions' })), {
    name: 'login', query: { redirect: '/submissions', reason: 'expired' },
  })
  assert.equal(getAuthToken(), '')
  assert.equal(session.user, null)
})

test('401 on a background API call and CSV export invalidates the shared session', async () => {
  for (const request of [() => apiRequest('/forms'), () => exportSubmissionsCsv({ form_id: 1 })]) {
    setAuthToken('valid')
    globalThis.fetch = async () => json(identity())
    await restoreSession()
    globalThis.fetch = async () => json({ message: 'Sessão expirada' }, 401)
    await assert.rejects(request, { status: 401 })
    assert.equal(getAuthToken(), '')
    assert.equal(session.user, null)
  }
})

test('forbidden API actions preserve the current session', async () => {
  setAuthToken('valid')
  globalThis.fetch = async () => json(identity())
  await restoreSession()
  globalThis.fetch = async () => json({ message: 'Acesso negado' }, 403)
  await assert.rejects(() => apiRequest('/audit-logs'), { status: 403 })
  assert.equal(getAuthToken(), 'valid')
  assert.equal(session.user.role, 'user')
})

test('API downtime and inactive accounts show a retry page without deleting the token', async () => {
  for (const status of [403, 503, 'offline']) {
    setAuthToken(`valid-${status}`)
    globalThis.fetch = async () => {
      if (status === 'offline') throw new Error('Failed to fetch')
      return json({ message: 'Acesso indisponível' }, status)
    }
    assert.deepEqual(await guardRoute(route({ fullPath: '/forms/1' })), {
      name: 'session-error', query: { redirect: '/forms/1' },
    })
    assert.ok(getAuthToken())
    assert.ok(session.error)
    globalThis.fetch = async () => json(identity())
    assert.equal(await guardRoute(route()), true)
    assert.equal(session.error, '')
  }
})

test('login verifies the session and returns to an internal requested page', async () => {
  const calls = []
  globalThis.fetch = async (url, options) => {
    calls.push(url)
    if (url.endsWith('/login')) {
      assert.deepEqual(JSON.parse(options.body), { email: 'teste@example.com', password: 'example', tenant_slug: 'demo' })
      assert.equal(options.headers.get('Authorization'), null)
      return json({ data: { token: 'new-session' } })
    }
    return json(identity('admin'))
  }
  await signIn({ email: 'teste@example.com', password: 'example', tenantSlug: 'demo' })
  assert.deepEqual(calls, ['/api/auth/login', '/api/auth/me'])
  assert.equal(session.user.role, 'admin')
  assert.equal(await guardRoute(route({ name: 'login', meta: { public: true }, query: { redirect: '/submissions/3' } })), '/submissions/3')
})

test('invalid login does not invalidate an unrelated existing token', async () => {
  setAuthToken('existing')
  globalThis.fetch = async () => json({ message: 'Credenciais inválidas' }, 401)
  await assert.rejects(() => login({ email: 'a@b.com', password: 'wrong' }), { status: 401 })
  assert.equal(getAuthToken(), 'existing')
})

test('late responses from an old session cannot erase or restore a newer identity', async () => {
  setAuthToken('old')
  let respond
  globalThis.fetch = () => new Promise((resolve) => { respond = resolve })
  const oldSession = restoreSession()
  setAuthToken('new')
  globalThis.fetch = async () => json(identity('admin'))
  await restoreSession()
  respond(json({ message: 'Old token expired' }, 401))
  await oldSession
  assert.equal(getAuthToken(), 'new')
  assert.equal(session.user.role, 'admin')

  setAuthToken('old-success')
  globalThis.fetch = () => new Promise((resolve) => { respond = resolve })
  const oldSuccess = restoreSession()
  clearAuthToken()
  respond(json(identity('admin-sistema')))
  await oldSuccess
  assert.equal(session.user, null)
})

test('logout clears local state immediately even when the backend is unavailable', async () => {
  setAuthToken('valid')
  globalThis.fetch = async () => json(identity())
  await restoreSession()
  globalThis.fetch = async (_url, options) => {
    assert.equal(options.headers.Authorization, 'Bearer valid')
    throw new Error('offline')
  }
  const leaving = signOut()
  assert.equal(getAuthToken(), '')
  assert.equal(session.user, null)
  await leaving
})

test('token changes from another tab invalidate cached identity', async () => {
  setAuthToken('valid')
  globalThis.fetch = async () => json(identity('admin'))
  await restoreSession()
  window.localStorage.setItem('fe_token', 'other-tab')
  syncAuthToken({ storageArea: window.localStorage, key: 'fe_token' })
  assert.equal(session.user, null)
  globalThis.fetch = async () => json(identity('user'))
  await restoreSession()
  assert.equal(canAdminister.value, false)
})

test('return paths reject external URLs and authentication redirect loops', () => {
  for (const path of ['https://example.com', '//example.com', '/\\example.com', '/login', '/LOGIN/', '/login/?redirect=/login', '/session-error', '/SESSION-ERROR', '/forbidden', null, ['/', '/login']]) {
    assert.equal(safeReturnPath(path), '/')
  }
  assert.equal(safeReturnPath('/forms/2/fill?draft=3'), '/forms/2/fill?draft=3')
})

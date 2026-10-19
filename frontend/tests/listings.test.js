import { test } from 'node:test'
import assert from 'node:assert/strict'
import { effectScope } from 'vue'
import { adminListTenants, listAllForms, listAuditLogs, listForms, listSubmissions, listTenantUsers } from '../src/api.js'
import { useListing, validatePeriod } from '../src/composables/useListing.js'
import { buildExportFilters, formatDate, versionStatus } from '../src/utils/display.js'

globalThis.window = { localStorage: { getItem: () => null } }
const pageData = (page, total = 45, perPage = 20) => ({ data: {
  rows: [{ id: page }], pagination: { current_page: page, total, per_page: perPage, last_page: Math.max(1, Math.ceil(total / perPage)) },
} })

test('API adapters send only supported filters, including page and period', async () => {
  const urls = []
  globalThis.fetch = async (url) => {
    urls.push(new URL(url, 'http://localhost'))
    return new Response('{}', { headers: { 'Content-Type': 'application/json' } })
  }
  await listForms({ search: 'Saúde & Educação', sort: 'created_at', order: 'desc', page: 7 })
  await listAllForms({ search: 'Cadastro', sort: 'name', order: 'asc' })
  await listSubmissions({ page: 3, per_page: 10, status: 'draft', form_id: 4, date_from: '2026-01-01', date_to: '2026-02-01', unknown: 'ignored' })
  await listAuditLogs({ page: 2, per_page: 50, action: 'LOGIN_SUCCESS', user_id: 2, entity_type: 'form', entity_id: 3, date_from: '2026-10-01', date_to: '2026-10-17' })
  await listTenantUsers(7, { page: 4, per_page: 20, search: 'unsupported' })
  await adminListTenants({ page: 2, per_page: 100 })
  assert.deepEqual(Object.fromEntries(urls[0].searchParams), { search: 'Saúde & Educação', sort: 'created_at', order: 'desc' })
  assert.equal(urls[1].searchParams.get('scope'), 'all')
  assert.deepEqual(Object.fromEntries(urls[2].searchParams), { page: '3', per_page: '10', status: 'draft', form_id: '4', date_from: '2026-01-01', date_to: '2026-02-01' })
  assert.equal(urls[3].searchParams.get('date_to'), '2026-10-17')
  assert.equal(urls[3].searchParams.get('entity_id'), '3')
  assert.equal(urls[4].pathname, '/api/tenants/7/users')
  assert.deepEqual(Object.fromEntries(urls[4].searchParams), { page: '4', per_page: '20' })
  assert.deepEqual(Object.fromEntries(urls[5].searchParams), { page: '2', per_page: '100' })
})

test('applying filters resets the page; pagination preserves applied filters, not edits', async () => {
  const requests = []
  const listing = useListing(async (params) => { requests.push(params); return pageData(params.page) }, 'rows', { search: '' })
  await listing.load()
  await listing.setPage(2)
  listing.filters.search = '  cadastro  '
  await listing.apply()
  assert.equal(listing.page.value, 1)
  listing.filters.search = 'unapplied edit'
  await listing.setPage(2)
  assert.deepEqual(requests.at(-1), { search: 'cadastro', page: 2, per_page: 20 })
  await listing.setPerPage(10)
  assert.equal(requests.at(-1).page, 1)
  assert.equal(requests.at(-1).per_page, 10)
  await listing.reset()
  assert.equal(requests.at(-1).search, '')
})

test('an older response cannot overwrite newer results or loading state', async () => {
  const responses = []
  const signals = []
  const listing = useListing((_params, options) => {
    signals.push(options.signal)
    return new Promise((resolve, reject) => responses.push({ resolve, reject }))
  }, 'rows')
  const oldRequest = listing.load()
  const newRequest = listing.load()
  assert.equal(signals[0].aborted, true)
  responses[1].resolve(pageData(1))
  await newRequest
  responses[0].reject(new Error('Old request failed'))
  await oldRequest
  assert.equal(listing.state.items[0].id, 1)
  assert.equal(listing.state.error, '')
  assert.equal(listing.state.loading, false)
})

test('unmount aborts pending requests and prevents late state updates', async () => {
  let respond, signal, listing
  const scope = effectScope()
  scope.run(() => {
    listing = useListing((_params, options) => { signal = options.signal; return new Promise(resolve => { respond = resolve }) }, 'rows')
  })
  const pending = listing.load()
  scope.stop()
  assert.equal(signal.aborted, true)
  respond(pageData(1))
  await pending
  assert.deepEqual(listing.state.items, [])
})

test('request errors clear stale results and recover on retry', async () => {
  let fail = false
  const listing = useListing(async () => {
    if (fail) throw { body: { message: 'Erro de validação', errors: { page: ['Página inválida.'] } } }
    return pageData(1)
  }, 'rows')
  await listing.load()
  fail = true
  await listing.load()
  assert.equal(listing.state.error, 'Página inválida.')
  assert.deepEqual(listing.state.items, [])
  assert.equal(listing.state.pagination, null)
  fail = false
  await listing.load()
  assert.equal(listing.state.error, '')
  assert.equal(listing.state.items.length, 1)
})

test('a page removed by data changes returns to the last available page', async () => {
  const requests = []
  let total = 45
  const listing = useListing(async (params) => { requests.push(params.page); return pageData(params.page, total) }, 'rows')
  await listing.load()
  await listing.setPage(3)
  total = 5
  await listing.load()
  assert.deepEqual(requests, [1, 3, 3, 1])
  assert.equal(listing.page.value, 1)
  assert.equal(listing.state.loading, false)
})

test('forms use unpaginated search and total without inventing server pagination', async () => {
  const listing = useListing(async (params) => {
    assert.deepEqual(params, { search: 'Nome', sort: 'name', order: 'asc' })
    return { data: { forms: [{ id: 7 }], total: 1 } }
  }, 'forms', { search: 'Nome', sort: 'name', order: 'asc' }, { paginated: false })
  await listing.load()
  assert.equal(listing.state.total, 1)
  assert.equal(listing.state.pagination, null)
})

test('invalid periods do not trigger HTTP requests; reset restores a usable list', async () => {
  let calls = 0
  const listing = useListing(async () => { calls++; return pageData(1) }, 'rows', { date_from: '', date_to: '' }, { validate: validatePeriod })
  listing.filters.date_from = '2026-10-17'
  listing.filters.date_to = '2026-10-01'
  await listing.apply()
  assert.equal(calls, 0)
  assert.match(listing.state.error, /data final/)
  await listing.reset()
  assert.equal(calls, 1)
  assert.equal(listing.state.error, '')
})

test('CSV exports the full applied selection without pagination and rejects drafts', () => {
  const filters = { form_id: '4', status: 'submitted', version_id: '9', date_from: '2026-10-01', date_to: '2026-10-17', page: 3, per_page: 10 }
  assert.deepEqual(buildExportFilters(filters), { form_id: 4, version_ids: [9], date_from: '2026-10-01', date_to: '2026-10-17' })
  assert.deepEqual(buildExportFilters({ form_id: '4', status: '' }), { form_id: 4 })
  assert.throws(() => buildExportFilters({ form_id: '', status: 'submitted' }), /formulário/)
  assert.throws(() => buildExportFilters({ form_id: '4', status: 'draft' }), /somente submissões enviadas/)
})

test('version status is independent from form activation; missing dates remain explicit', () => {
  assert.equal(versionStatus({ is_published: false }), 'Versão em rascunho')
  assert.equal(versionStatus({ is_published: true }), 'Versão publicada')
  assert.equal(versionStatus(null), 'Sem versão')
  assert.equal(formatDate(null), '—')
  assert.equal(formatDate('invalid'), '—')
})

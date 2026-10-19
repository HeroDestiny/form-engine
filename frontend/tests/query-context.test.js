import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createSSRApp, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createMemoryHistory, createRouter } from 'vue-router'
import { useQueryListing } from '../src/composables/useQueryListing.js'
import { decodeListQuery, encodeListQuery } from '../src/utils/listQuery.js'
import { buildExportFilters } from '../src/utils/display.js'
import { auditEntityPath } from '../src/utils/auditEntity.js'
import { csvDataRows } from '../src/utils/csv.js'

const defaults = { form_id: '', version_id: '', user_id: '', status: 'submitted', date_from: '', date_to: '' }
async function setup(path) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }] })
  await router.push(path)
  let listing
  const requests = []
  const app = createSSRApp({ setup() {
    listing = useQueryListing(async params => {
      requests.push(params)
      return { data: { rows: [{ id: 42 }], pagination: { current_page: params.page, last_page: 5, total: 45 } } }
    }, 'rows', defaults)
    return () => h('div')
  } })
  app.use(router)
  await renderToString(app)
  return { router, listing, requests }
}

test('query parsing rejects arrays, invalid identifiers and unsupported pagination sizes', () => {
  const result = decodeListQuery({ form_id: ['4'], version_id: '-1', user_id: '2147483648', status: 'other', page: '0', per_page: '999' }, defaults)
  assert.deepEqual(result, { filters: defaults, page: 1, perPage: 20 })
  assert.deepEqual(encodeListQuery(defaults, defaults), {})
})

test('reload and explicit return from detail restore filters, page and page size', async () => {
  const path = '/submissions?form_id=4&version_id=9&user_id=5&status=draft&page=3&per_page=10'
  const first = await setup(path)
  await first.listing.load()
  assert.deepEqual(first.requests[0], { ...defaults, form_id: '4', version_id: '9', user_id: '5', status: 'draft', page: 3, per_page: 10 })
  const returnTo = first.router.currentRoute.value.fullPath
  await first.router.push({ path: '/submissions/42', query: { returnTo } })
  const restored = await setup(first.router.currentRoute.value.query.returnTo)
  await restored.listing.load()
  assert.deepEqual(restored.requests[0], first.requests[0])
})

test('query actions preserve applied criteria during pagination and reset page on new filters', async () => {
  const { listing, router } = await setup('/submissions?form_id=4&page=3')
  await listing.load()
  listing.filters.user_id = '99'
  await listing.setPage(4)
  assert.equal(router.currentRoute.value.query.page, '4')
  assert.equal(router.currentRoute.value.query.user_id, undefined)
  await listing.apply()
  assert.equal(router.currentRoute.value.query.user_id, '99')
  assert.equal(router.currentRoute.value.query.page, undefined)
})

test('clearing unsaved edits on an already default URL resets the controls too', async () => {
  const { listing, router } = await setup('/submissions')
  listing.filters.form_id = '99'
  await listing.reset()
  assert.equal(listing.filters.form_id, '')
  assert.equal(listing.applied.value.form_id, '')
  assert.equal(router.currentRoute.value.fullPath, '/submissions')
})

test('multiple CSV versions and separator are explicit; unsupported user filters cannot silently widen export', () => {
  assert.deepEqual(buildExportFilters({ form_id: '4', version_ids: ['9', '10', '9'], separator: ';' }), { form_id: 4, version_ids: [9, 10], separator: ';' })
  assert.throws(() => buildExportFilters({ form_id: '4', user_id: '5' }), /usuário/)
})

test('audit entity links require existence and a supported internal destination', () => {
  assert.equal(auditEntityPath({ entity_exists: true, log: { entity_type: 'form', entity_id: 4 } }), '/forms/4')
  assert.equal(auditEntityPath({ entity_exists: true, log: { entity_type: 'form_submission', entity_id: 9 } }), '/submissions/9')
  assert.equal(auditEntityPath({ entity_exists: false, log: { entity_type: 'form', entity_id: 4 } }), null)
  assert.equal(auditEntityPath({ entity_exists: true, log: { entity_type: 'unknown', entity_id: 4 } }), null)
})

test('CSV statistics count actual records, including multiline quoted cells and escaped quotes', () => {
  assert.equal(csvDataRows('\uFEFFid;name\r\n1;"First\nsecond"\r\n2;"Say ""hello"""\r\n'), 2)
  assert.equal(csvDataRows('id,name\n1,test'), 1)
  assert.equal(csvDataRows('id,name\r\n'), 0)
  assert.equal(csvDataRows(''), 0)
})

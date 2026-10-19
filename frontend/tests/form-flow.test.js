import { test } from 'node:test'
import assert from 'node:assert/strict'
import { useFormFill } from '../src/composables/useFormFill.js'
import { fieldPayload, fieldValidationErrors, initialValues, nextFieldOrder, submissionValues } from '../src/utils/formValues.js'

const fields = [{ id: 1, name: 'name', type: 'text', order: 10 }, { id: 2, name: 'amount', type: 'number', order: 20 }, { id: 3, name: 'choices', type: 'checkbox', order: 30 }]
const detail = { submission: { id: 7, status: 'draft' }, form: { id: 1 }, user: { id: 2 }, version: { id: 10, version_number: 1 }, values: [{ name: 'name', value: 'Saved' }, { name: 'choices', value: ['a'] }] }
function client(overrides = {}) {
  return {
    getForm: async () => ({ data: { form: { id: 1, is_active: true } } }),
    listFormVersions: async () => ({ data: { versions: [{ id: 10, version_number: 1, is_published: true }, { id: 20, version_number: 2, is_published: true }] } }),
    listFields: async () => ({ data: { fields } }),
    listDrafts: async () => ({ data: { drafts: [{ id: 7 }] } }),
    getSubmission: async () => ({ data: structuredClone(detail) }),
    saveDraft: async () => ({ data: { draft: { id: 7 } } }),
    updateDraft: async () => ({ data: { draft: { id: 7 } } }),
    finalizeDraft: async () => ({ data: { submission: { id: 7 } } }),
    submitForm: async () => ({ data: { submission: { id: 8 } } }),
    ...overrides,
  }
}

test('new responses select the latest published version even if the API order changes', async () => {
  const form = useFormFill(1, 2, client())
  await form.initialize()
  assert.equal(form.state.version.id, 20)
  assert.equal(form.canWrite.value, true)
  assert.equal(form.dirty.value, false)
  assert.deepEqual(form.values.choices, [])
  form.values.amount = 0
  assert.equal(form.dirty.value, true)
})

test('resuming a draft restores answers and pins its original version', async () => {
  const requests = []
  const form = useFormFill(1, 2, client({
    listFields: async (_form, version) => { requests.push(version); return { data: { fields } } },
    finalizeDraft: async (...args) => { requests.push(args); return { data: { submission: { id: 7 } } } },
  }))
  await form.initialize('7')
  assert.equal(form.state.version.id, 10)
  assert.equal(form.values.name, 'Saved')
  assert.deepEqual(form.values.choices, ['a'])
  form.values.amount = 0
  await form.save(true)
  assert.equal(requests[0], 10)
  assert.deepEqual(requests[1], [1, '7', { name: 'Saved', amount: 0, choices: ['a'] }])
  assert.equal(form.state.completedId, 7)
})

test('saving again updates the same draft, including clearing all answers', async () => {
  const requests = []
  const form = useFormFill(1, 2, client({
    saveDraft: async (...args) => { requests.push(['create', ...args]); return { data: { draft: { id: 7 } } } },
    updateDraft: async (...args) => { requests.push(['update', ...args]); return { data: { draft: { id: 7 } } } },
  }))
  await form.initialize()
  form.values.name = 'Partial'
  await form.save()
  assert.equal(form.dirty.value, false)
  form.values.name = ''
  await form.save()
  assert.deepEqual(requests, [['create', 1, { name: 'Partial' }, 20], ['update', 1, 7, {}]])
})

test('failed or foreign draft loads fail closed instead of submitting stale answers', async () => {
  for (const changed of [{ user: { id: 99 } }, { form: { id: 99 } }, { submission: { id: 7, status: 'submitted' } }]) {
    const form = useFormFill(1, 2, client({ getSubmission: async () => ({ data: { ...detail, ...changed } }) }))
    await form.initialize()
    form.values.name = 'Stale value'
    await form.switchContext(7)
    assert.equal(form.canWrite.value, false)
    assert.equal(form.state.version, null)
    assert.deepEqual(Object.keys(form.values), [])
    assert.equal(await form.save(true), false)
  }
})

test('inactive forms and unpublished forms cannot save or send responses', async () => {
  for (const overrides of [
    { getForm: async () => ({ data: { form: { id: 1, is_active: false } } }) },
    { listFormVersions: async () => ({ data: { versions: [{ id: 20, is_published: false }] } }) },
  ]) {
    const form = useFormFill(1, 2, client(overrides))
    await form.initialize()
    assert.equal(form.canWrite.value, false)
    assert.ok(form.state.error)
    assert.equal(await form.save(), false)
  }
})

test('server validation maps checkbox item errors to the field and preserves answers', async () => {
  const form = useFormFill(1, 2, client({ submitForm: async () => { throw { body: { message: 'Dados inválidos', errors: { 'values.name': ['Nome obrigatório'], 'values.choices.0': ['Opção inválida'] } } } } }))
  await form.initialize()
  form.values.choices = ['invalid']
  assert.equal(await form.save(true), false)
  assert.equal(form.state.fieldErrors.name, 'Nome obrigatório')
  assert.equal(form.state.fieldErrors.choices, 'Opção inválida')
  assert.deepEqual(form.values.choices, ['invalid'])
  assert.equal(form.canWrite.value, true)
})

test('save and submit cannot race, and a confirmed submission cannot be sent twice', async () => {
  let resolve, calls = 0
  const form = useFormFill(1, 2, client({ submitForm: async () => { calls++; return new Promise(done => { resolve = done }) } }))
  await form.initialize()
  const pending = form.save(true)
  assert.equal(await form.save(), false)
  assert.equal(await form.save(true), false)
  resolve({ data: { submission: { id: 8 } } })
  await pending
  assert.equal(await form.save(true), false)
  assert.equal(calls, 1)
  assert.equal(form.dirty.value, false)
  await form.switchContext()
  assert.equal(form.state.completedId, null)
  assert.equal(form.values.name, '')
  assert.equal(form.canWrite.value, true)
})

test('a draft-list refresh failure cannot turn a successful send into an apparent failure', async () => {
  const form = useFormFill(1, 2, client({ listDrafts: async () => { throw new Error('offline') } }))
  await form.initialize()
  assert.equal(await form.save(true), true)
  assert.equal(form.state.completedId, 8)
  assert.equal(form.state.error, '')
  assert.ok(form.state.draftsError)
})

test('field payload validates choice values, preserves labels with separators and clears choice configuration on type change', () => {
  const field = { label: ' Choice ', name: 'choice', type: 'select', is_required: true, order: 10, optionsText: 'a|Label | extra\nb|Second' }
  assert.deepEqual(fieldPayload(field).options, [{ value: 'a', label: 'Label | extra' }, { value: 'b', label: 'Second' }])
  assert.throws(() => fieldPayload({ ...field, optionsText: '' }), /opção/)
  assert.throws(() => fieldPayload({ ...field, optionsText: 'a|One\na|Two' }), /diferente/)
  assert.equal('options' in fieldPayload({ ...field, type: 'text' }), false)
  assert.equal(nextFieldOrder(fields), 31)
})

test('zero values, checkbox arrays and special but valid field names survive payload building', () => {
  const special = [...fields, { name: '__proto__', type: 'text' }]
  const values = initialValues(special, [{ name: '__proto__', value: 'safe' }, { name: 'amount', value: 0 }])
  assert.equal(Object.hasOwn(values, '__proto__'), true)
  const payload = submissionValues(special, values)
  assert.equal(payload.amount, 0)
  assert.equal(payload.__proto__, 'safe')
  assert.equal(Object.hasOwn(payload, 'choices'), false)
  assert.equal(fieldValidationErrors({ body: { errors: { 'values.__proto__': ['Invalid'] } } }).__proto__, 'Invalid')
})

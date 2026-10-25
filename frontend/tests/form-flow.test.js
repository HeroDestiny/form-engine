import { test } from 'node:test'
import assert from 'node:assert/strict'
import { useFormFill } from '../src/composables/useFormFill.js'
import { fieldPayload, fieldValidationErrors, formFillProgress, initialValues, isFieldFilled, nextFieldOrder, previewFieldFromDraft, submissionValues } from '../src/utils/formValues.js'
import {
  evaluateCondition,
  filterVisibleFields,
  getFieldChoices,
  getFieldCondition,
  isFieldVisible,
  pruneHiddenValues,
} from '../src/utils/conditionalLogic.js'
import {
  BLOCK_PRESETS,
  FIELD_PRESETS,
  duplicateFieldDraft,
  generateUniqueFieldName,
} from '../src/utils/formPresets.js'
import {
  extractUniqueSections,
  getFieldSection,
  getFieldSectionDescription,
  getFieldWidth,
  getFieldWidthClass,
  getFormLayoutMode,
  groupFieldsBySection,
} from '../src/utils/formLayout.js'

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

test('file attachment fields support initial values, progress calculation, submission payload and optional extension filters', () => {
  const fileField = { id: 99, name: 'comprovante', label: 'Comprovante', type: 'file', is_required: true, order: 1 }
  const allFields = [fileField]

  // Initial value is null
  const initial = initialValues(allFields)
  assert.equal(initial.comprovante, null)
  assert.equal(isFieldFilled(initial.comprovante), false)

  // Progress is 0%
  const progBefore = formFillProgress(allFields, initial)
  assert.equal(progBefore.requiredFilled, 0)
  assert.equal(progBefore.percent, 0)

  // Filled with attachment metadata
  const attached = { comprovante: { path: 'tenants/1/attachments/2026/10/hash.pdf', name: 'recibo.pdf', size: 102400 } }
  assert.equal(isFieldFilled(attached.comprovante), true)

  const progAfter = formFillProgress(allFields, attached)
  assert.equal(progAfter.requiredFilled, 1)
  assert.equal(progAfter.percent, 100)

  // Submission values includes attachment object
  const payload = submissionValues(allFields, attached)
  assert.deepEqual(payload.comprovante, attached.comprovante)

  // Field payload with optionsText parses allowed extensions
  const fieldWithExt = { ...fileField, optionsText: 'pdf|PDF\npng|PNG' }
  const fieldPayloadRes = fieldPayload(fieldWithExt)
  assert.deepEqual(fieldPayloadRes.options, [{ value: 'pdf', label: 'PDF' }, { value: 'png', label: 'PNG' }])
})

test('conditional logic engine evaluates equals, not_equals, in, filled, empty and circular safety', () => {
  // Test operators
  assert.equal(evaluateCondition({ field: 'tipo', operator: 'equals', value: 'fisica' }, { tipo: 'fisica' }), true)
  assert.equal(evaluateCondition({ field: 'tipo', operator: 'equals', value: 'fisica' }, { tipo: 'juridica' }), false)
  assert.equal(evaluateCondition({ field: 'tipo', operator: 'not_equals', value: 'fisica' }, { tipo: 'juridica' }), true)
  assert.equal(evaluateCondition({ field: 'tipo', operator: 'not_equals', value: 'fisica' }, { tipo: 'fisica' }), false)

  // 'in' operator with array value (e.g. checkbox)
  assert.equal(evaluateCondition({ field: 'hobbies', operator: 'in', value: 'esporte' }, { hobbies: ['leitura', 'esporte'] }), true)
  assert.equal(evaluateCondition({ field: 'hobbies', operator: 'in', value: 'cinema' }, { hobbies: ['leitura', 'esporte'] }), false)

  // 'in' operator with comma-separated rule value against scalar field answer
  assert.equal(evaluateCondition({ field: 'estado', operator: 'in', value: 'SP, RJ, MG' }, { estado: 'RJ' }), true)
  assert.equal(evaluateCondition({ field: 'estado', operator: 'in', value: 'SP, RJ, MG' }, { estado: 'RS' }), false)

  // 'filled' and 'empty' operators
  assert.equal(evaluateCondition({ field: 'nome', operator: 'filled' }, { nome: 'Paulo' }), true)
  assert.equal(evaluateCondition({ field: 'nome', operator: 'filled' }, { nome: '   ' }), false)
  assert.equal(evaluateCondition({ field: 'nome', operator: 'filled' }, { nome: null }), false)
  assert.equal(evaluateCondition({ field: 'nome', operator: 'empty' }, { nome: '' }), true)
  assert.equal(evaluateCondition({ field: 'nome', operator: 'empty' }, { nome: 'Paulo' }), false)

  // Field definitions
  const testFields = [
    { name: 'tipo_pessoa', type: 'radio' },
    { name: 'cpf', type: 'text', condition: { field: 'tipo_pessoa', operator: 'equals', value: 'fisica' } },
    { name: 'cnpj', type: 'text', condition: { field: 'tipo_pessoa', operator: 'equals', value: 'juridica' } },
    { name: 'razao_social', type: 'text', condition: { field: 'cnpj', operator: 'filled' } },
  ]

  // Filter visible fields
  const visibleFisica = filterVisibleFields(testFields, { tipo_pessoa: 'fisica', cpf: '123' })
  assert.deepEqual(visibleFisica.map(f => f.name), ['tipo_pessoa', 'cpf'])

  const visibleJuridica = filterVisibleFields(testFields, { tipo_pessoa: 'juridica', cnpj: '456' })
  assert.deepEqual(visibleJuridica.map(f => f.name), ['tipo_pessoa', 'cnpj', 'razao_social'])

  // Prune hidden values (fields, values)
  const dirtyAnswers = { tipo_pessoa: 'fisica', cpf: '123', cnpj: 'orphan_cnpj', razao_social: 'orphan_razao' }
  const cleanAnswers = pruneHiddenValues(testFields, dirtyAnswers)
  assert.deepEqual({ ...cleanAnswers }, { tipo_pessoa: 'fisica', cpf: '123' })

  // Circular dependency safety (A depends on B, B depends on A without infinite recursion)
  const circularFields = [
    { name: 'a', condition: { field: 'b', operator: 'equals', value: '1' } },
    { name: 'b', condition: { field: 'a', operator: 'equals', value: '1' } },
  ]
  assert.equal(isFieldVisible(circularFields[0], { a: '0', b: '0' }, circularFields), false)
})

test('form presets and duplicateDraft generate collision-free names and valid payload structures', () => {
  // Integrity of presets
  assert.ok(FIELD_PRESETS.length >= 6)
  for (const preset of FIELD_PRESETS) {
    assert.ok(preset.id && preset.name && preset.label && preset.type)
  }

  assert.ok(BLOCK_PRESETS.length >= 3)
  for (const block of BLOCK_PRESETS) {
    assert.ok(block.id && block.name && block.fields.length > 0)
    for (const f of block.fields) {
      assert.ok(f.name && f.label && f.type)
    }
  }

  // generateUniqueFieldName
  const existing = [{ name: 'cpf' }, { name: 'cpf_2' }, { name: 'telefone' }]
  assert.equal(generateUniqueFieldName('cpf', existing), 'cpf_3')
  assert.equal(generateUniqueFieldName('novo_campo', existing), 'novo_campo')
  assert.equal(generateUniqueFieldName('Endereço & Número!', existing), 'endereco_numero')

  // duplicateFieldDraft
  const original = {
    id: 10,
    label: 'Telefone Celular',
    name: 'telefone',
    type: 'text',
    is_required: true,
    order: 5,
    options: null,
    condition: { field: 'tipo', operator: 'equals', value: '1' },
  }
  const cloned = duplicateFieldDraft(original, existing)
  assert.equal(cloned.label, 'Telefone Celular (Cópia)')
  assert.equal(cloned.name, 'telefone_2')
  assert.equal(cloned.type, 'text')
  assert.equal(cloned.is_required, true)
  assert.deepEqual(cloned.condition, original.condition)

  // fieldPayload with condition
  const payloadWithCond = fieldPayload({
    label: 'Campo Condicional',
    name: 'cond_field',
    type: 'text',
    is_required: true,
    order: 6,
    condition: { field: 'parent', operator: 'equals', value: 'yes' },
  })
  assert.deepEqual(payloadWithCond.condition, { field: 'parent', operator: 'equals', value: 'yes' })
})

test('useFormFill dynamic progress and pruneHiddenValues react to conditional fields', async () => {
  const condFields = [
    { id: 1, name: 'tipo', type: 'radio', is_required: true, order: 1 },
    { id: 2, name: 'cpf', type: 'text', is_required: true, order: 2, options: { condition: { field: 'tipo', operator: 'equals', value: 'fisica' } } },
    { id: 3, name: 'cnpj', type: 'text', is_required: true, order: 3, options: { condition: { field: 'tipo', operator: 'equals', value: 'juridica' } } },
  ]
  const requests = []
  const form = useFormFill(1, 2, client({
    listFields: async () => ({ data: { fields: condFields } }),
    saveDraft: async (...args) => { requests.push(args); return { data: { draft: { id: 7 } } } },
  }))
  await form.initialize()

  // Initially only 'tipo' is visible because 'tipo' has no value yet
  assert.deepEqual(form.visibleFields.value.map(f => f.name), ['tipo'])
  assert.equal(form.progress.value.requiredTotal, 1)
  assert.equal(form.progress.value.percent, 0)

  // Answering 'fisica' reveals 'cpf' and updates required count to 2
  form.values.tipo = 'fisica'
  assert.deepEqual(form.visibleFields.value.map(f => f.name), ['tipo', 'cpf'])
  assert.equal(form.progress.value.requiredTotal, 2)
  assert.equal(form.progress.value.requiredFilled, 1)
  assert.equal(form.progress.value.percent, 50)

  // Answering 'cpf' reaches 100%
  form.values.cpf = '12345678900'
  form.values.cnpj = 'orphan_value_should_be_pruned'
  assert.equal(form.progress.value.percent, 100)

  // Saving prunes the hidden cnpj value
  await form.save()
  assert.equal(requests.length, 1)
  assert.deepEqual(requests[0][1], { tipo: 'fisica', cpf: '12345678900' })
  assert.equal('cnpj' in requests[0][1], false)
})

test('form layout, logical cards and wizard stepper support section grouping and column widths', () => {
  const sampleFields = [
    { id: 1, name: 'nome', label: 'Nome Completo', type: 'text', options: { section: 'Identificação', section_description: 'Dados pessoais', width: 'full' } },
    { id: 2, name: 'cpf', label: 'CPF', type: 'text', options: { section: 'Identificação', width: 'half' } },
    { id: 3, name: 'rg', label: 'RG', type: 'text', options: { section: 'Identificação', width: 'half' } },
    { id: 4, name: 'cep', label: 'CEP', type: 'text', options: { section: 'Endereço', width: 'third' } },
    { id: 5, name: 'cidade', label: 'Cidade', type: 'text', options: { section: 'Endereço', width: 'half' } },
    { id: 6, name: 'observacoes', label: 'Observações', type: 'textarea' }, // Unassigned section
  ]

  // getFieldSection and getFieldWidth
  assert.equal(getFieldSection(sampleFields[0]), 'Identificação')
  assert.equal(getFieldSection(sampleFields[5]), null)
  assert.equal(getFieldSectionDescription(sampleFields[0]), 'Dados pessoais')
  assert.equal(getFieldWidth(sampleFields[0]), 'full')
  assert.equal(getFieldWidth(sampleFields[1]), 'half')
  assert.equal(getFieldWidth(sampleFields[3]), 'third')
  assert.equal(getFieldWidth(sampleFields[5]), 'full')

  // getFieldWidthClass polymorphic support (strings, objects, options.width, json string, undefined)
  assert.equal(getFieldWidthClass('full'), 'col-12')
  assert.equal(getFieldWidthClass('half'), 'col-6')
  assert.equal(getFieldWidthClass('third'), 'col-4')
  assert.equal(getFieldWidthClass(sampleFields[0]), 'col-12')
  assert.equal(getFieldWidthClass(sampleFields[1]), 'col-6')
  assert.equal(getFieldWidthClass(sampleFields[3]), 'col-4')
  assert.equal(getFieldWidthClass({ width: 'half' }), 'col-6')
  assert.equal(getFieldWidthClass({ options: { width: 'third' } }), 'col-4')
  assert.equal(getFieldWidthClass({ options: '{"width":"half"}' }), 'col-6')
  assert.equal(getFieldWidthClass(undefined), 'col-12')
  assert.equal(getFieldWidthClass(null), 'col-12')

  const draftPreview = previewFieldFromDraft({
    label: 'Email Corporativo',
    name: 'email_corp',
    type: 'email',
    width: 'half',
    section: 'Contato',
  })
  assert.equal(getFieldWidth(draftPreview), 'half')
  assert.equal(getFieldWidthClass(draftPreview), 'col-6')
  assert.equal(draftPreview.options.width, 'half')
  assert.equal(draftPreview.options.section, 'Contato')

  // extractUniqueSections
  const sections = extractUniqueSections(sampleFields)
  assert.deepEqual(sections, ['Endereço', 'Identificação'])

  // groupFieldsBySection
  const groups = groupFieldsBySection(sampleFields)
  assert.equal(groups.length, 3)
  assert.equal(groups[0].title, 'Identificação')
  assert.equal(groups[0].description, 'Dados pessoais')
  assert.equal(groups[0].fields.length, 3)
  assert.equal(groups[1].title, 'Endereço')
  assert.equal(groups[1].fields.length, 2)
  assert.equal(groups[2].title, 'Campos Gerais')
  assert.equal(groups[2].isDefault, true)
  assert.equal(groups[2].fields.length, 1)

  // getFormLayoutMode
  assert.equal(getFormLayoutMode(sampleFields), 'cards')
  const wizardFields = [{ ...sampleFields[0], options: { layout_mode: 'wizard' } }]
  assert.equal(getFormLayoutMode(wizardFields), 'wizard')

  // duplicateFieldDraft preserves layout & section
  const duplicated = duplicateFieldDraft(sampleFields[0], sampleFields)
  assert.equal(duplicated.section, 'Identificação')
  assert.equal(duplicated.section_description, 'Dados pessoais')
  assert.equal(duplicated.width, 'full')

  // fieldPayload with layout attributes
  const payload = fieldPayload({
    label: 'Complemento',
    name: 'complemento',
    type: 'text',
    is_required: false,
    order: 10,
    section: 'Endereço',
    section_description: 'Dados residenciais',
    width: 'third',
    layout_mode: 'wizard',
  })
  assert.equal(payload.section, 'Endereço')
  assert.equal(payload.section_description, 'Dados residenciais')
  assert.equal(payload.width, 'third')
  assert.equal(payload.layout_mode, 'wizard')
})

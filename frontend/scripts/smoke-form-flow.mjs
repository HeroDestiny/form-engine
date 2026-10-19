// Local integration check. Creates a labeled test form and two submissions.
// FORM_ENGINE_SMOKE_PASSWORD must be the existing local development password.
import assert from 'node:assert/strict'
import * as api from '../src/api.js'
import { buildExportFilters } from '../src/utils/display.js'

const base = new URL(process.env.FORM_ENGINE_SMOKE_URL || 'http://127.0.0.1:8000')
if (!['127.0.0.1', 'localhost'].includes(base.hostname)) throw new Error('Este teste só pode usar uma API local.')
if (!process.env.FORM_ENGINE_SMOKE_PASSWORD) throw new Error('Defina FORM_ENGINE_SMOKE_PASSWORD para executar o teste local.')
const nativeFetch = globalThis.fetch
globalThis.fetch = (path, options) => nativeFetch(new URL(path, base), options)
const storage = new Map()
let exportedBlob
globalThis.window = {
  localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
  URL: { createObjectURL: blob => { exportedBlob = blob; return 'blob:local-test' }, revokeObjectURL() {} },
}
globalThis.document = { createElement: () => ({ click() {}, remove() {} }), body: { appendChild() {} } }

let formId, succeeded = false
try {
  await api.login({ email: process.env.FORM_ENGINE_SMOKE_EMAIL || 'admin@formengine.local', password: process.env.FORM_ENGINE_SMOKE_PASSWORD, tenantSlug: 'demo' })
  const name = `Validação funcional ${new Date().toISOString()}`
  const created = await api.createForm({ name, description: 'Formulário de teste do fluxo funcional do frontend: oito tipos de campo, versões e rascunhos.' })
  formId = created.data.form.id
  const first = (await api.listFormVersions(formId)).data.versions[0]
  const choices = [{ value: 'a', label: 'Primeira opção' }, { value: 'b', label: 'Segunda opção' }]
  const definitions = [
    ['name', 'Nome', 'text'], ['notes', 'Observações', 'textarea'], ['amount', 'Quantidade', 'number'], ['email', 'E-mail', 'email'],
    ['date', 'Data', 'date'], ['selection', 'Seleção', 'select'], ['channel', 'Canal', 'radio'], ['interests', 'Interesses', 'checkbox'],
  ]
  for (const [index, [fieldName, label, type]] of definitions.entries()) {
    await api.createField(formId, first.id, { name: fieldName, label, type, is_required: fieldName !== 'notes', order: index + 1, ...(['select', 'radio', 'checkbox'].includes(type) ? { options: choices } : {}) })
  }
  const scratch = await api.createField(formId, first.id, { name: 'scratch', label: 'Campo temporário', type: 'text', is_required: false, order: 9 })
  const scratchId = scratch.data.field?.id ?? scratch.data.id
  await api.deleteField(formId, first.id, scratchId)
  await api.publishFormVersion(formId, first.id)
  const draft = (await api.saveDraft(formId, { name: 'Parcial' }, first.id)).data.draft
  await api.updateDraft(formId, draft.id, {})
  assert.equal((await api.getSubmission(draft.id)).data.values.find(field => field.name === 'name').value, null)

  const second = (await api.createFormVersion(formId)).data.version
  assert.equal((await api.listFields(formId, second.id)).data.fields.length, 8)
  await api.createField(formId, second.id, { name: 'new_requirement', label: 'Novo requisito', type: 'text', is_required: true, order: 9 })
  await api.publishFormVersion(formId, second.id)
  const answers = { name: 'Pessoa de teste', notes: 'Fluxo integrado', amount: 0, email: 'teste@example.com', date: '2026-10-17', selection: 'a', channel: 'b', interests: ['a', 'b'] }
  await assert.rejects(() => api.finalizeDraft(formId, draft.id, {}), error => error.status === 422 && !!error.body.errors['values.name'])
  await api.updateDraft(formId, draft.id, answers)
  const finalized = (await api.finalizeDraft(formId, draft.id, answers)).data.submission
  assert.equal(finalized.id, draft.id)
  const detail = (await api.getSubmission(finalized.id)).data
  assert.equal(detail.submission.status, 'submitted')
  assert.equal(detail.version.id, first.id)
  assert.deepEqual(detail.values.find(field => field.name === 'interests').value, ['a', 'b'])
  const latest = (await api.submitForm(formId, { ...answers, new_requirement: 'Resposta da nova versão' }, second.id)).data.submission
  await api.exportSubmissionsCsv(buildExportFilters({ form_id: formId, status: 'submitted' }))
  const csv = await exportedBlob.text()
  assert.ok(csv.includes('Pessoa de teste'))
  assert.ok(csv.includes('Novo requisito') || csv.includes('new_requirement'))
  await api.updateFormStatus(formId, false)
  assert.equal((await api.getForm(formId)).data.form.is_active, false)
  await api.updateFormStatus(formId, true)
  succeeded = true
  console.log(JSON.stringify({ result: 'passed', formId, versions: [first.id, second.id], submissions: [finalized.id, latest.id], csvBytes: exportedBlob.size, url: `http://127.0.0.1:5173/forms/${formId}` }, null, 2))
} finally {
  if (!succeeded && formId) await api.updateFormStatus(formId, false).catch(() => {})
  await api.logout()
}

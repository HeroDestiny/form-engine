import { computed, reactive, ref } from 'vue'
import * as api from '../api.js'
import { fieldValidationErrors, formFillProgress, initialValues, sortedFields, submissionValues } from '../utils/formValues.js'
import { filterVisibleFields, pruneHiddenValues } from '../utils/conditionalLogic.js'

export function useFormFill(formId, userId, client = api) {
  const state = reactive({
    loading: false,
    switching: false,
    saving: false,
    autosaving: false,
    submitting: false,
    form: null,
    version: null,
    fields: [],
    drafts: [],
    draftsError: '',
    error: '',
    fieldErrors: Object.create(null),
    message: '',
    activeDraftId: null,
    completedId: null,
    lastSavedAt: null,
    ready: false,
  })
  const values = reactive(Object.create(null))
  const baseline = ref('{}')
  const busy = computed(() => state.loading || state.switching || state.saving || state.submitting)
  const dirty = computed(() => JSON.stringify(values) !== baseline.value)
  const canWrite = computed(() => state.ready && state.form?.is_active && !!state.version && !state.completedId && !busy.value)
  const visibleFields = computed(() => filterVisibleFields(state.fields, values))
  const progress = computed(() => formFillProgress(visibleFields.value, values))

  function replaceValues(next) {
    Object.keys(values).forEach(key => delete values[key])
    Object.assign(values, next)
    baseline.value = JSON.stringify(values)
  }

  function fail(error) {
    state.fieldErrors = fieldValidationErrors(error)
    state.error = error?.body?.message || error?.message || 'Não foi possível concluir a operação.'
  }

  async function loadDrafts() {
    state.draftsError = ''
    try { state.drafts = (await client.listDrafts(formId))?.data?.drafts ?? [] }
    catch { state.draftsError = 'Não foi possível atualizar os rascunhos. Tente novamente.' }
  }

  async function openContext(draftId) {
    state.ready = false
    state.error = ''
    state.fieldErrors = Object.create(null)
    state.message = ''
    state.completedId = null
    state.lastSavedAt = null
    try {
      const form = (await client.getForm(formId))?.data?.form
      if (!form?.is_active) throw new Error('Este formulário não está disponível para preenchimento.')
      let version, saved = []
      if (draftId) {
        const detail = (await client.getSubmission(draftId))?.data
        if (!detail || detail.submission?.status !== 'draft' || String(detail.form?.id) !== String(formId) || String(detail.user?.id) !== String(userId)) throw new Error('Rascunho não encontrado para sua conta neste formulário.')
        version = detail.version
        saved = detail.values ?? []
        state.lastSavedAt = detail.submission?.updated_at || detail.submission?.created_at || null
      } else {
        const versions = (await client.listFormVersions(formId))?.data?.versions ?? []
        version = versions.filter(item => item.is_published).sort((a, b) => b.version_number - a.version_number)[0]
      }
      if (!version?.id) throw new Error('Este formulário ainda não tem uma versão publicada.')
      const fields = sortedFields((await client.listFields(formId, version.id))?.data?.fields ?? [])
      state.form = form
      state.version = version
      state.fields = fields
      state.activeDraftId = draftId || null
      replaceValues(initialValues(fields, saved))
      state.ready = true
      if (draftId) state.message = 'Rascunho carregado na versão original.'
    } catch (error) {
      state.version = null
      state.fields = []
      state.activeDraftId = null
      replaceValues({})
      fail(error)
    }
  }

  async function initialize(draftId = '') {
    if (busy.value) return
    state.loading = true
    try { await openContext(draftId); await loadDrafts() }
    finally { state.loading = false }
  }

  async function switchContext(draftId = '') {
    if (busy.value) return
    state.switching = true
    try { await openContext(draftId) }
    finally { state.switching = false }
  }

  async function save(finalize = false, options = {}) {
    if (!canWrite.value) return false
    const isAutosave = !finalize && !!options.autosave
    state[finalize ? 'submitting' : 'saving'] = true
    state.autosaving = isAutosave
    state.error = ''
    state.fieldErrors = Object.create(null)
    if (!isAutosave) state.message = ''
    try {
      const payload = submissionValues(visibleFields.value, pruneHiddenValues(state.fields, values))
      const response = finalize
        ? state.activeDraftId ? await client.finalizeDraft(formId, state.activeDraftId, payload) : await client.submitForm(formId, payload, state.version.id)
        : state.activeDraftId ? await client.updateDraft(formId, state.activeDraftId, payload) : await client.saveDraft(formId, payload, state.version.id)
      baseline.value = JSON.stringify(values)
      if (finalize) {
        state.completedId = response.data.submission.id
        state.ready = false
        state.activeDraftId = null
        state.lastSavedAt = null
        state.message = 'Formulário enviado com sucesso.'
      } else {
        state.activeDraftId = response.data.draft.id
        state.lastSavedAt = new Date().toISOString()
        if (!isAutosave) state.message = 'Rascunho salvo com sucesso.'
      }
      await loadDrafts()
      return true
    } catch (error) {
      fail(error)
      return false
    } finally {
      state.saving = false
      state.autosaving = false
      state.submitting = false
    }
  }

  async function autosave() {
    if (!canWrite.value || !dirty.value) return false
    const payload = submissionValues(visibleFields.value, pruneHiddenValues(state.fields, values))
    if (!state.activeDraftId && Object.keys(payload).length === 0) return false
    return save(false, { autosave: true })
  }

  return { state, values, busy, dirty, canWrite, progress, visibleFields, initialize, switchContext, loadDrafts, save, autosave }
}

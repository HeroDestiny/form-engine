<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getForm, listFormVersions, listFields, submitForm, saveDraft, listDrafts, getSubmission } from '../api'

const route = useRoute()
const router = useRouter()

const formId = computed(() => route.params.formId)

const state = reactive({
  loading: false,
  error: '',
  form: null,
  version: null,
  fields: [],
  submitting: false,
  savingDraft: false,
  message: '',
  drafts: [],
  draftsError: '',
  loadingDrafts: false,
  loadingDraftValues: false,
  selectedDraftId: null,
})

const formValues = reactive({})
const checkboxValues = reactive({})

async function loadData() {
  state.loading = true
  state.error = ''
  state.message = ''

  try {
    const formData = await getForm(formId.value)
    state.form = formData?.data?.form ?? null

    const versionsData = await listFormVersions(formId.value)
    const versions = versionsData?.data?.versions ?? []
    const published = versions.filter((v) => v.is_published)

    if (!published.length) {
      state.error = 'Este formulário não está disponível para preenchimento (nenhuma versão publicada).'
      return
    }

    // assume first item is latest published (API already orders by version_number desc)
    state.version = published[0]

    const fieldsData = await listFields(formId.value, state.version.id)
    state.fields = (fieldsData?.data?.fields ?? []).slice().sort((a, b) => a.order - b.order)

    state.fields.forEach((field) => {
      if (field.type === 'checkbox') {
        if (!Array.isArray(checkboxValues[field.name])) {
          checkboxValues[field.name] = []
        }
      } else if (formValues[field.name] === undefined) {
        formValues[field.name] = ''
      }
    })

    await loadDrafts()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar formulário para preenchimento.'
  } finally {
    state.loading = false
  }
}

async function loadDrafts() {
  state.loadingDrafts = true
  state.draftsError = ''

  try {
    const data = await listDrafts(formId.value)
    state.drafts = data?.data?.drafts ?? []
  } catch (error) {
    state.draftsError = error?.body?.message || 'Erro ao carregar rascunhos.'
  } finally {
    state.loadingDrafts = false
  }
}

function applyDraftValues(values) {
  Object.keys(formValues).forEach((key) => {
    formValues[key] = ''
  })
  Object.keys(checkboxValues).forEach((key) => {
    checkboxValues[key] = []
  })

  if (!values || typeof values !== 'object') return

  state.fields.forEach((field) => {
    const raw = values[field.name]
    if (raw === undefined || raw === null || raw === '') {
      return
    }

    if (field.type === 'checkbox') {
      const arr = Array.isArray(raw)
        ? raw
        : String(raw)
            .split(',')
            .map((v) => v.trim())
            .filter(Boolean)
      checkboxValues[field.name] = arr
    } else {
      formValues[field.name] = raw
    }
  })
}

async function handleLoadDraft(draftId) {
  if (!draftId) return

  state.loadingDraftValues = true
  state.error = ''
  state.message = ''

  try {
    const data = await getSubmission(draftId)
    const valuesArray = data?.data?.values ?? []

    const valuesObject = {}
    if (Array.isArray(valuesArray)) {
      valuesArray.forEach((item) => {
        if (item && item.name !== undefined) {
          valuesObject[item.name] = item.value
        }
      })
    }

    applyDraftValues(valuesObject)
    state.message = data?.message || 'Rascunho carregado. Você pode continuar o preenchimento.'
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar rascunho.'
  } finally {
    state.loadingDraftValues = false
  }
}

function buildPayloadValues() {
  const payload = {}

  state.fields.forEach((field) => {
    if (field.type === 'checkbox') {
      const arr = checkboxValues[field.name] || []
      if (arr.length) {
        payload[field.name] = arr.join(',')
      }
    } else if (formValues[field.name] !== undefined && formValues[field.name] !== '') {
      payload[field.name] = formValues[field.name]
    }
  })

  return payload
}

async function handleSubmit() {
  state.submitting = true
  state.error = ''
  state.message = ''

  try {
    const values = buildPayloadValues()
    const data = await submitForm(formId.value, values)
    state.message = data?.message || 'Formulário enviado com sucesso.'
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.error = error?.body?.message || 'Erro ao enviar formulário.'
    }
  } finally {
    state.submitting = false
  }
}

async function handleSaveDraft() {
  state.savingDraft = true
  state.error = ''
  state.message = ''

  try {
    const values = buildPayloadValues()
    const data = await saveDraft(formId.value, values)
    state.message = data?.message || 'Rascunho salvo com sucesso.'
    await loadDrafts()
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.error = error?.body?.message || 'Erro ao salvar rascunho.'
    }
  } finally {
    state.savingDraft = false
  }
}

onMounted(() => {
  if (!formId.value) {
    router.replace('/')
    return
  }

  loadData()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Preenchimento de formulário</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">
        Voltar para a lista
      </button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <template v-if="state.loading">
          <p class="panel-subtitle">Carregando formulário…</p>
        </template>

        <template v-else>
          <h2>{{ state.form?.name || 'Formulário' }}</h2>
          <p class="panel-subtitle">
            {{ state.form?.description || 'Preencha os dados abaixo.' }}
          </p>
          <p class="panel-subtitle" v-if="state.version">
            Versão {{ state.version.version_number }}
          </p>

          <p v-if="state.error" class="feedback error">{{ state.error }}</p>
          <p v-if="state.message" class="panel-subtitle">{{ state.message }}</p>

          <div v-if="state.loadingDrafts" class="panel-subtitle">Carregando rascunhos…</div>
          <p v-else-if="state.draftsError" class="feedback error">{{ state.draftsError }}</p>

          <div v-if="!state.loadingDrafts && state.drafts.length" class="field">
            <span>Rascunhos salvos</span>
            <select
              v-model="state.selectedDraftId"
              :disabled="state.loadingDraftValues"
              @change="handleLoadDraft(state.selectedDraftId)"
            >
              <option value="">Selecione um rascunho…</option>
              <option v-for="d in state.drafts" :key="d.id" :value="d.id">
                #{{ d.id }} · {{ d.created_at }}
              </option>
            </select>
          </div>

          <form class="form" @submit.prevent="handleSubmit">
            <div v-for="field in state.fields" :key="field.id" class="field">
              <span>
                {{ field.label }}
                <small v-if="field.is_required">(obrigatório)</small>
              </span>

              <input
                v-if="['text', 'email', 'number', 'date'].includes(field.type)"
                v-model="formValues[field.name]"
                :type="field.type === 'text' ? 'text' : field.type"
              />

              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="formValues[field.name]"
                rows="3"
              ></textarea>

              <select v-else-if="field.type === 'select'" v-model="formValues[field.name]">
                <option value="">Selecione…</option>
                <option v-for="opt in field.options || []" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>

              <div v-else-if="field.type === 'radio'" class="field-options">
                <label v-for="opt in field.options || []" :key="opt.value" class="field-option">
                  <input
                    v-model="formValues[field.name]"
                    type="radio"
                    :value="opt.value"
                  />
                  <span>{{ opt.label }}</span>
                </label>
              </div>

              <div v-else-if="field.type === 'checkbox'" class="field-options">
                <label v-for="opt in field.options || []" :key="opt.value" class="field-option">
                  <input
                    v-model="checkboxValues[field.name]"
                    type="checkbox"
                    :value="opt.value"
                  />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
            </div>

            <div class="panel-actions" style="margin-top: 1rem;">
              <button
                class="ghost-button"
                type="button"
                :disabled="state.savingDraft || state.loadingDraftValues"
                @click.prevent="handleSaveDraft"
              >
                <span v-if="!state.savingDraft">Salvar rascunho</span>
                <span v-else>Salvando…</span>
              </button>

              <button class="primary-button" type="submit" :disabled="state.submitting">
                <span v-if="!state.submitting">Enviar formulário</span>
                <span v-else>Enviando…</span>
              </button>
            </div>
          </form>
        </template>
      </section>
    </main>
  </div>
</template>

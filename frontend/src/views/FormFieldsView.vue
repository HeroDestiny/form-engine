<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getForm, listFormVersions, listFields, createField, deleteField } from '../api'

const route = useRoute()
const router = useRouter()

const formId = computed(() => route.params.formId)
const versionId = computed(() => route.params.versionId)

const state = reactive({
  loading: false,
  error: '',
  form: null,
  version: null,
  fields: [],
  saving: false,
})

const newField = reactive({
  label: '',
  name: '',
  type: 'text',
  is_required: true,
  order: 1,
  optionsText: '',
})

const fieldTypes = [
  { value: 'text', label: 'Texto' },
  { value: 'textarea', label: 'Texto longo' },
  { value: 'number', label: 'Número' },
  { value: 'email', label: 'E-mail' },
  { value: 'date', label: 'Data' },
  { value: 'select', label: 'Seleção (dropdown)' },
  { value: 'radio', label: 'Opção única (radio)' },
  { value: 'checkbox', label: 'Múltipla escolha (checkbox)' },
]

const needsOptions = computed(() =>
  ['select', 'radio', 'checkbox'].includes(newField.type),
)

async function loadData() {
  state.loading = true
  state.error = ''

  try {
    const formData = await getForm(formId.value)
    state.form = formData?.data?.form ?? null

    const versionsData = await listFormVersions(formId.value)
    const versions = versionsData?.data?.versions ?? []
    state.version = versions.find((v) => String(v.id) === String(versionId.value)) ?? null

    const fieldsData = await listFields(formId.value, versionId.value)
    state.fields = fieldsData?.data?.fields ?? []
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar campos.'
  } finally {
    state.loading = false
  }
}

function parseOptions(text) {
  const lines = text.split('\n').map((l) => l.trim()).filter(Boolean)
  return lines.map((line) => {
    const [valuePart, labelPart] = line.split('|')
    const value = (valuePart || '').trim()
    const label = (labelPart || valuePart || '').trim()
    return { value, label }
  })
}

async function handleCreateField() {
  state.saving = true
  state.error = ''

  try {
    const payload = {
      label: newField.label,
      name: newField.name,
      type: newField.type,
      is_required: !!newField.is_required,
      order: Number(newField.order) || 1,
    }

    if (needsOptions.value) {
      payload.options = parseOptions(newField.optionsText)
    }

    await createField(formId.value, versionId.value, payload)

    newField.label = ''
    newField.name = ''
    newField.type = 'text'
    newField.is_required = true
    newField.order = (state.fields?.length || 0) + 1
    newField.optionsText = ''

    await loadData()
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else if (error.status === 422 || error.status === 400) {
      state.error = error?.body?.message || 'Versão não pode ser editada.'
    } else if (error.status === 403) {
      state.error = 'Você não tem permissão para editar campos deste formulário.'
    } else {
      state.error = error?.body?.message || 'Erro ao criar campo.'
    }
  } finally {
    state.saving = false
  }
}

async function handleDeleteField(fieldId) {
  if (!window.confirm('Remover este campo?')) return

  state.error = ''

  try {
    await deleteField(formId.value, versionId.value, fieldId)
    await loadData()
  } catch (error) {
    if (error.status === 400 || error.status === 422) {
      state.error = error?.body?.message || 'Campo não pode ser removido.'
    } else {
      state.error = error?.body?.message || 'Erro ao remover campo.'
    }
  }
}

onMounted(() => {
  if (!formId.value || !versionId.value) {
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
          <p>Campos da versão</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push(`/forms/${formId}`)">
        Voltar para o formulário
      </button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <template v-if="state.loading">
          <p class="panel-subtitle">Carregando campos…</p>
        </template>

        <template v-else>
          <div class="panel-header">
            <div>
              <h2>{{ state.form?.name || 'Formulário' }}</h2>
              <p class="panel-subtitle">
                Versão {{ state.version?.version_number || versionId }} · {{ state.fields.length }} campo(s)
              </p>
            </div>
          </div>

          <p v-if="state.error" class="feedback error">{{ state.error }}</p>

          <div class="forms-grid">
            <div class="form-card">
              <h3>Campos atuais</h3>
              <div v-if="!state.fields.length" class="empty-state">
                <p>Nenhum campo cadastrado ainda.</p>
              </div>
              <ul v-else class="fields-list">
                <li v-for="field in state.fields" :key="field.id" class="field-row">
                  <div class="field-row-main">
                    <strong>{{ field.label }}</strong>
                    <span class="field-row-meta">
                      {{ field.name }} · {{ field.type }} · ordem {{ field.order }}
                      <span v-if="field.is_required">· obrigatório</span>
                    </span>
                  </div>
                  <button class="ghost-button" type="button" @click="handleDeleteField(field.id)">
                    Remover
                  </button>
                </li>
              </ul>
            </div>

            <div class="form-card">
              <h3>Novo campo</h3>
              <form class="form" @submit.prevent="handleCreateField">
                <label class="field">
                  <span>Rótulo</span>
                  <input v-model="newField.label" type="text" required minlength="2" maxlength="255" />
                </label>

                <label class="field">
                  <span>Nome técnico</span>
                  <input
                    v-model="newField.name"
                    type="text"
                    required
                    pattern="^[a-z0-9_]+$"
                    placeholder="ex: numero_beneficio"
                  />
                </label>

                <label class="field">
                  <span>Tipo</span>
                  <select v-model="newField.type">
                    <option v-for="t in fieldTypes" :key="t.value" :value="t.value">
                      {{ t.label }}
                    </option>
                  </select>
                </label>

                <label class="field">
                  <span>Ordem</span>
                  <input v-model.number="newField.order" type="number" min="1" />
                </label>

                <label class="field field-inline">
                  <input v-model="newField.is_required" type="checkbox" />
                  <span>Campo obrigatório</span>
                </label>

                <label v-if="needsOptions" class="field">
                  <span>Opções (uma por linha, opcionalmente "valor|rótulo")</span>
                  <textarea
                    v-model="newField.optionsText"
                    rows="4"
                    placeholder="sim|Sim
nao|Não"
                  ></textarea>
                </label>

                <button class="primary-button" type="submit" :disabled="state.saving">
                  <span v-if="!state.saving">Adicionar campo</span>
                  <span v-else>Salvando…</span>
                </button>
              </form>
            </div>
          </div>
        </template>
      </section>
    </main>
  </div>
</template>

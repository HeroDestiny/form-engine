<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { getForm, listFormVersions, listFields, createField, updateField, deleteField } from '../api'
import { canManageForms } from '../auth/session.js'
import { fieldPayload, nextFieldOrder, sortedFields } from '../utils/formValues.js'
import { errorMessage } from '../composables/useListing.js'

const route = useRoute()
const formId = route.params.formId
const versionId = route.params.versionId
const state = reactive({ loading: false, loaded: false, error: '', message: '', form: null, version: null, fields: [], saving: false, editingId: null })
const newField = reactive({ label: '', name: '', type: 'text', is_required: true, order: 1, optionsText: '' })
const fieldTypes = [
  { value: 'text', label: 'Texto' }, { value: 'textarea', label: 'Texto longo' },
  { value: 'number', label: 'Número' }, { value: 'email', label: 'E-mail' },
  { value: 'date', label: 'Data' }, { value: 'select', label: 'Seleção' },
  { value: 'radio', label: 'Opção única' }, { value: 'checkbox', label: 'Múltipla escolha' },
]
const needsOptions = computed(() => ['select', 'radio', 'checkbox'].includes(newField.type))
const isEditing = computed(() => state.editingId !== null)
const editable = computed(() => state.loaded && state.version && !state.version.is_published && canManageForms.value)

async function loadData() {
  state.loading = true
  state.loaded = false
  state.error = ''
  try {
    const form = await getForm(formId)
    const versions = await listFormVersions(formId)
    const version = versions?.data?.versions?.find(item => String(item.id) === String(versionId))
    if (!version) throw new Error('Versão não encontrada neste formulário.')
    const fields = await listFields(formId, versionId)
    state.form = form.data.form
    state.version = version
    state.fields = sortedFields(fields?.data?.fields ?? [])
    state.loaded = true
  } catch (error) { state.error = error.body ? errorMessage(error) : error.message }
  finally { state.loading = false }
}

function cancelEdit() {
  state.editingId = null
  Object.assign(newField, { label: '', name: '', type: 'text', is_required: true, order: nextFieldOrder(state.fields), optionsText: '' })
}

function startEditField(field) {
  if (!editable.value || state.saving) return
  state.editingId = field.id
  Object.assign(newField, { label: field.label, name: field.name, type: field.type, is_required: field.is_required, order: field.order, optionsText: (field.options || []).map(option => `${option.value}|${option.label}`).join('\n') })
  state.error = ''
  state.message = ''
}

async function handleCreateField() {
  if (!editable.value || state.saving) return
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    const payload = fieldPayload(newField)
    if (isEditing.value) await updateField(formId, versionId, state.editingId, payload)
    else await createField(formId, versionId, payload)
    await loadData()
    cancelEdit()
    state.message = 'Campo salvo com sucesso.'
  } catch (error) { state.error = error.body ? errorMessage(error) : error.message }
  finally { state.saving = false }
}

async function handleDeleteField(fieldId) {
  if (!editable.value || state.saving || !window.confirm('Remover este campo da versão em edição?')) return
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    await deleteField(formId, versionId, fieldId)
    await loadData()
    if (state.editingId === fieldId) cancelEdit()
    if (!isEditing.value) newField.order = nextFieldOrder(state.fields)
    state.message = 'Campo removido.'
  } catch (error) { state.error = error.body ? errorMessage(error) : error.message }
  finally { state.saving = false }
}
onMounted(async () => { await loadData(); cancelEdit() })
</script>

<template>
  <section class="panel panel-forms">
    <template v-if="state.loading">
      <p class="panel-subtitle">Carregando campos…</p>
    </template>

    <template v-else-if="state.loaded">
      <div class="panel-header">
        <div>
          <h1>{{ state.form?.name || 'Formulário' }}</h1>
          <p class="panel-subtitle">
            Versão {{ state.version?.version_number || versionId }} · {{ state.fields.length }} campo(s)
          </p>
        </div>
      </div>

      <p v-if="state.version?.is_published" class="feedback" role="status">Esta versão está publicada e seus campos não podem ser alterados. Crie uma nova versão no detalhe do formulário para fazer alterações.</p>
      <p v-if="state.message" class="feedback" role="status">{{ state.message }}</p>
      <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

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
              <div v-if="editable" class="field-row-actions">
                <button class="ghost-button" type="button" :disabled="state.saving" @click="startEditField(field)">
                  Editar
                </button>
                <button class="ghost-button" type="button" :disabled="state.saving" @click="handleDeleteField(field.id)">
                  Remover
                </button>
              </div>
            </li>
          </ul>
        </div>

        <div v-if="editable" class="form-card">
          <h3>{{ isEditing ? 'Editar campo' : 'Novo campo' }}</h3>
          <form class="form" @submit.prevent="handleCreateField">
            <fieldset class="response-fields form" :disabled="state.saving">
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
                maxlength="100"
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
              <input v-model.number="newField.order" type="number" min="1" max="2147483647" step="1" required />
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

            <div class="form-actions-inline">
              <button class="primary-button" type="submit" :disabled="state.saving">
                <span v-if="!state.saving">
                  {{ isEditing ? 'Salvar alterações' : 'Adicionar campo' }}
                </span>
                <span v-else>Salvando…</span>
              </button>
              <button v-if="isEditing" class="ghost-button" type="button" :disabled="state.saving" @click="cancelEdit">
                Cancelar edição
              </button>
            </div>
            </fieldset>
          </form>
        </div>
      </div>
    </template>
    <div v-else><p class="feedback error" role="alert">{{ state.error }}</p><button class="ghost-button" type="button" @click="loadData">Tentar novamente</button></div>
  </section>
</template>

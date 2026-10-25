<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getForm, listFormVersions, listFields, createField, updateField, deleteField } from '../api'
import { canManageForms } from '../auth/session.js'
import { fieldPayload, nextFieldOrder, previewFieldFromDraft, reorderFieldPair, sortedFields } from '../utils/formValues.js'
import { FIELD_PRESETS, BLOCK_PRESETS, generateUniqueFieldName, duplicateFieldDraft } from '../utils/formPresets.js'
import { getFieldCondition, getFieldChoices, filterVisibleFields } from '../utils/conditionalLogic.js'
import {
  extractUniqueSections,
  getFieldSection,
  getFieldSectionDescription,
  getFieldWidth,
  getFieldWidthClass,
  getFormLayoutMode,
  groupFieldsBySection,
} from '../utils/formLayout.js'
import { errorMessage } from '../composables/useListing.js'
import { usePageBreadcrumbs } from '../composables/useBreadcrumbs.js'
import { pushToast } from '../composables/useToast.js'
import FormResponseField from '../components/FormResponseField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'

const route = useRoute()
const formId = route.params.formId
const versionId = route.params.versionId
const { setBreadcrumbs } = usePageBreadcrumbs()

const state = reactive({
  loading: false,
  loaded: false,
  error: '',
  message: '',
  form: null,
  version: null,
  fields: [],
  saving: false,
  editingId: null,
  deletingField: null,
})

const viewMode = ref('split')
const previewValues = reactive({})
const drawerOpen = ref(false)
const presetFilterTab = ref('all')
const presetSearch = ref('')
const insertingBlock = ref(false)
const versionLayoutMode = ref('cards')
const previewStepIndex = ref(0)

const newField = reactive({
  label: '',
  name: '',
  type: 'text',
  is_required: true,
  order: 1,
  optionsText: '',
  is_conditional: false,
  condition_field: '',
  condition_operator: 'equals',
  condition_value: '',
  section: '',
  section_description: '',
  width: 'full',
})

const fieldTypes = [
  { value: 'text', label: 'Texto curto' },
  { value: 'textarea', label: 'Texto longo' },
  { value: 'number', label: 'Número' },
  { value: 'email', label: 'E-mail' },
  { value: 'date', label: 'Data' },
  { value: 'select', label: 'Lista de seleção' },
  { value: 'radio', label: 'Opção única (rádio)' },
  { value: 'checkbox', label: 'Múltipla escolha (caixas)' },
  { value: 'file', label: 'Arquivo / Anexo' },
]
const typeLabels = Object.fromEntries(fieldTypes.map(item => [item.value, item.label]))

const conditionOperators = [
  { value: 'equals', label: 'É igual a' },
  { value: 'not_equals', label: 'É diferente de' },
  { value: 'in', label: 'Está contido em (múltipla escolha)' },
  { value: 'filled', label: 'Está preenchido / não vazio' },
  { value: 'empty', label: 'Está vazio / em branco' },
]

const widthLabels = {
  full: '100% (Largura total)',
  half: '50% (Metade)',
  third: '33% (1/3)',
}

const needsOptions = computed(() => ['select', 'radio', 'checkbox'].includes(newField.type))
const isFileField = computed(() => newField.type === 'file')
const isEditing = computed(() => state.editingId !== null)
const editable = computed(() => state.loaded && state.version && !state.version.is_published && canManageForms.value)

const existingSections = computed(() => extractUniqueSections(state.fields))
const groupedBuilderSections = computed(() => groupFieldsBySection(state.fields))

watch(() => state.fields, (fields) => {
  versionLayoutMode.value = getFormLayoutMode(fields)
}, { immediate: true })

const availableConditionTargets = computed(() => {
  return state.fields.filter(f => f.id !== state.editingId && f.name !== newField.name)
})

const selectedTargetField = computed(() => {
  return availableConditionTargets.value.find(f => f.name === newField.condition_field) || null
})

const targetFieldChoices = computed(() => {
  if (!selectedTargetField.value) return []
  return getFieldChoices(selectedTargetField.value)
})

const previewFields = computed(() => {
  const base = state.fields.filter(item => item.id !== state.editingId)
  const condition = newField.is_conditional && newField.condition_field
    ? {
        field: newField.condition_field,
        operator: newField.condition_operator,
        value: newField.condition_value,
      }
    : null
  const draft = editable.value ? previewFieldFromDraft({
    ...newField,
    condition,
    layout_mode: versionLayoutMode.value,
    id: state.editingId || 'draft-preview',
  }) : null
  const all = sortedFields(draft ? [...base, draft] : state.fields)
  return filterVisibleFields(all, previewValues)
})

const groupedPreviewSections = computed(() => groupFieldsBySection(previewFields.value))
const totalPreviewSteps = computed(() => groupedPreviewSections.value.length)
const currentPreviewSection = computed(() => groupedPreviewSections.value[previewStepIndex.value] || null)

watch(totalPreviewSteps, count => {
  if (previewStepIndex.value >= count) {
    previewStepIndex.value = Math.max(0, count - 1)
  }
})

const filteredFieldPresets = computed(() => {
  const query = presetSearch.value.trim().toLowerCase()
  if (!query) return FIELD_PRESETS
  return FIELD_PRESETS.filter(p =>
    p.label.toLowerCase().includes(query) ||
    p.name.toLowerCase().includes(query) ||
    p.description.toLowerCase().includes(query) ||
    p.badge.toLowerCase().includes(query),
  )
})

const filteredBlockPresets = computed(() => {
  const query = presetSearch.value.trim().toLowerCase()
  if (!query) return BLOCK_PRESETS
  return BLOCK_PRESETS.filter(b =>
    b.name.toLowerCase().includes(query) ||
    b.description.toLowerCase().includes(query) ||
    b.badge.toLowerCase().includes(query),
  )
})

function formatConditionSummary(cond) {
  if (!cond || !cond.field) return ''
  const opMap = {
    equals: 'igual a',
    not_equals: 'diferente de',
    in: 'contido em',
    filled: 'preenchido',
    empty: 'em branco',
  }
  const opLabel = opMap[cond.operator] || cond.operator
  if (['filled', 'empty'].includes(cond.operator)) {
    return `Se “${cond.field}” estiver ${opLabel}`
  }
  return `Se “${cond.field}” for ${opLabel} “${cond.value ?? ''}”`
}

function syncPreviewDefaults(fields) {
  for (const field of fields) {
    if (field.type === 'checkbox' && !Array.isArray(previewValues[field.name])) {
      previewValues[field.name] = []
    } else if (field.type === 'file') {
      previewValues[field.name] = previewValues[field.name] ?? null
    } else if (field.type !== 'checkbox' && Array.isArray(previewValues[field.name])) {
      previewValues[field.name] = ''
    } else if (!(field.name in previewValues)) {
      previewValues[field.name] = field.type === 'checkbox' ? [] : (field.type === 'file' ? null : '')
    }
  }
}

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
    syncPreviewDefaults(state.fields)
    setBreadcrumbs([
      { label: 'Formulários', to: '/' },
      { label: state.form?.name || `Formulário #${formId}`, to: `/forms/${formId}` },
      { label: `Versão ${version.version_number} · Campos` },
    ])
    state.loaded = true
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.loading = false
  }
}

function cancelEdit() {
  state.editingId = null
  Object.assign(newField, {
    label: '',
    name: '',
    type: 'text',
    is_required: true,
    order: nextFieldOrder(state.fields),
    optionsText: '',
    is_conditional: false,
    condition_field: '',
    condition_operator: 'equals',
    condition_value: '',
    section: '',
    section_description: '',
    width: 'full',
  })
}

function startEditField(field) {
  if (!editable.value || state.saving) return
  state.editingId = field.id
  const cond = getFieldCondition(field)
  const choices = getFieldChoices(field)
  const sec = getFieldSection(field)
  const secDesc = getFieldSectionDescription(field)
  const w = getFieldWidth(field)
  Object.assign(newField, {
    label: field.label,
    name: field.name,
    type: field.type,
    is_required: field.is_required,
    order: field.order,
    optionsText: (choices || []).map(option => `${option.value}|${option.label}`).join('\n'),
    is_conditional: Boolean(cond && cond.field),
    condition_field: cond?.field || '',
    condition_operator: cond?.operator || 'equals',
    condition_value: cond?.value ?? '',
    section: sec || '',
    section_description: secDesc || '',
    width: w || 'full',
  })
  if (viewMode.value === 'preview') viewMode.value = 'split'
  state.error = ''
  state.message = ''
}

function fieldToApiPayload(field, nextOrder) {
  const payload = {
    label: field.label,
    name: field.name,
    type: field.type,
    is_required: !!field.is_required,
    order: nextOrder,
    layout_mode: versionLayoutMode.value,
  }
  const choices = getFieldChoices(field)
  const cond = getFieldCondition(field)
  const sec = getFieldSection(field)
  const secDesc = getFieldSectionDescription(field)
  const w = getFieldWidth(field)

  if (choices && choices.length) {
    payload.options = choices
  }
  if (cond && cond.field) {
    payload.condition = cond
  }
  if (sec) {
    payload.section = sec
  }
  if (secDesc) {
    payload.section_description = secDesc
  }
  if (w && w !== 'full') {
    payload.width = w
  }
  return payload
}

async function moveField(index, direction) {
  if (!editable.value || state.saving) return
  const pair = reorderFieldPair(state.fields, index, direction)
  if (!pair) return

  state.saving = true
  state.error = ''
  state.message = ''
  try {
    await updateField(formId, versionId, pair.current.id, fieldToApiPayload(pair.current, pair.currentNextOrder))
    await updateField(formId, versionId, pair.target.id, fieldToApiPayload(pair.target, pair.targetNextOrder))
    await loadData()
    if (!isEditing.value) newField.order = nextFieldOrder(state.fields)
    state.message = `Campo “${pair.current.label}” movido para a ordem ${pair.currentNextOrder}.`
    pushToast(state.message)
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.saving = false
  }
}

async function handleCreateField() {
  if (!editable.value || state.saving) return
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    const condition = newField.is_conditional && newField.condition_field
      ? {
          field: newField.condition_field,
          operator: newField.condition_operator,
          value: newField.condition_value,
        }
      : null

    const payload = fieldPayload({
      ...newField,
      condition,
      layout_mode: versionLayoutMode.value,
    })

    if (isEditing.value) await updateField(formId, versionId, state.editingId, payload)
    else await createField(formId, versionId, payload)

    await loadData()
    cancelEdit()
    state.message = 'Campo salvo com sucesso.'
    pushToast(state.message)
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.saving = false
  }
}

async function handleDuplicateField(field) {
  if (!editable.value || state.saving) return
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    const draft = duplicateFieldDraft(field, state.fields)
    const choices = getFieldChoices(field)
    const cond = getFieldCondition(field)
    const payload = {
      label: draft.label,
      name: draft.name,
      type: draft.type,
      is_required: draft.is_required,
      order: draft.order,
      section: draft.section,
      section_description: draft.section_description,
      width: draft.width,
      layout_mode: versionLayoutMode.value,
    }
    if (choices && choices.length) {
      payload.options = choices
    }
    if (cond && cond.field) {
      payload.condition = cond
    }
    await createField(formId, versionId, payload)
    await loadData()
    if (!isEditing.value) cancelEdit()
    state.message = `Campo duplicado com sucesso como “${draft.label}”.`
    pushToast(state.message)
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.saving = false
  }
}

async function setVersionLayoutMode(mode) {
  if (!editable.value || state.saving || versionLayoutMode.value === mode) return
  versionLayoutMode.value = mode
  if (state.fields.length > 0) {
    const firstField = state.fields[0]
    state.saving = true
    try {
      const payload = fieldToApiPayload(firstField, firstField.order)
      payload.layout_mode = mode
      await updateField(formId, versionId, firstField.id, payload)
      await loadData()
      pushToast(`Modo alterado para ${mode === 'wizard' ? 'Etapas (Wizard)' : 'Cards Contínuos'}.`)
    } catch (err) {
      state.error = err.body ? errorMessage(err) : err.message
    } finally {
      state.saving = false
    }
  }
}

function openPresetsDrawer() {
  presetSearch.value = ''
  drawerOpen.value = true
}

function closePresetsDrawer() {
  drawerOpen.value = false
}

function applyPresetToEditor(preset) {
  const uniqueName = generateUniqueFieldName(preset.name, state.fields)
  const choices = Array.isArray(preset.options) ? preset.options : []
  Object.assign(newField, {
    label: preset.label,
    name: uniqueName,
    type: preset.type,
    is_required: preset.is_required,
    order: nextFieldOrder(state.fields),
    optionsText: choices.map(opt => `${opt.value}|${opt.label}`).join('\n'),
    is_conditional: false,
    condition_field: '',
    condition_operator: 'equals',
    condition_value: '',
    section: preset.section || '',
    section_description: preset.section_description || '',
    width: preset.width || 'full',
  })
  closePresetsDrawer()
  pushToast(`Preset “${preset.label}” carregado no editor.`)
}

async function insertPresetDirectly(preset) {
  if (!editable.value || state.saving) return
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    const uniqueName = generateUniqueFieldName(preset.name, state.fields)
    const choices = Array.isArray(preset.options) ? preset.options : []
    const payload = {
      label: preset.label,
      name: uniqueName,
      type: preset.type,
      is_required: preset.is_required,
      order: nextFieldOrder(state.fields),
      section: preset.section || null,
      section_description: preset.section_description || null,
      width: preset.width || 'full',
      layout_mode: versionLayoutMode.value,
    }
    if (choices.length) {
      payload.options = choices
    }
    await createField(formId, versionId, payload)
    await loadData()
    if (!isEditing.value) cancelEdit()
    closePresetsDrawer()
    state.message = `Campo “${preset.label}” adicionado à versão.`
    pushToast(state.message)
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.saving = false
  }
}

async function insertBlockDirectly(block) {
  if (!editable.value || state.saving || insertingBlock.value) return
  insertingBlock.value = true
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    let currentOrder = nextFieldOrder(state.fields)
    const existing = [...state.fields]

    for (const fieldTemplate of block.fields) {
      const uniqueName = generateUniqueFieldName(fieldTemplate.name, existing)
      const choices = Array.isArray(fieldTemplate.options) ? fieldTemplate.options : []
      const payload = {
        label: fieldTemplate.label,
        name: uniqueName,
        type: fieldTemplate.type,
        is_required: fieldTemplate.is_required,
        order: currentOrder++,
        section: fieldTemplate.section || block.name,
        section_description: fieldTemplate.section_description || null,
        width: fieldTemplate.width || 'full',
        layout_mode: versionLayoutMode.value,
      }
      if (choices.length) {
        payload.options = choices
      }
      const created = await createField(formId, versionId, payload)
      existing.push(created?.data?.field ?? { name: uniqueName, order: payload.order })
    }

    await loadData()
    if (!isEditing.value) cancelEdit()
    closePresetsDrawer()
    state.message = `Bloco “${block.name}” (${block.fields.length} campos) adicionado com sucesso.`
    pushToast(state.message)
  } catch (error) {
    state.error = error.body ? errorMessage(error) : error.message
  } finally {
    state.saving = false
    insertingBlock.value = false
  }
}

onMounted(async () => {
  await loadData()
  cancelEdit()
})
</script>

<template>
  <div class="page-stack" :aria-busy="state.loading">
    <template v-if="state.loading">
      <span class="sr-only" role="status">Carregando campos…</span>
      <div class="surface-card skeleton-list" aria-hidden="true">
        <div class="skeleton-block w-50" style="height: 1.75rem;" />
        <div class="skeleton-block w-35" />
        <div v-for="n in 3" :key="n" class="skeleton-row">
          <div class="skeleton-lines">
            <div class="skeleton-block w-50" />
            <div class="skeleton-block w-80" />
          </div>
          <div class="skeleton-block btn" />
        </div>
      </div>
    </template>

    <template v-else-if="state.loaded">
      <div class="page-header">
        <div>
          <div class="card-heading" style="margin-bottom: 0.35rem;">
            <h1>{{ state.form?.name || 'Formulário' }}</h1>
            <StatusBadge
              :tone="state.version?.is_published ? 'success' : 'warning'"
              :label="state.version?.is_published ? `Versão ${state.version.version_number} publicada` : `Versão ${state.version?.version_number || versionId} em edição`"
            />
          </div>
          <p class="panel-subtitle">
            {{ state.fields.length }} campo(s) configurado(s) em {{ groupedBuilderSections.length }} grupo(s) lógico(s)
          </p>
        </div>

        <div class="builder-toolbar">
          <!-- Seletor de Modo de Apresentação no Preenchimento -->
          <div v-if="editable" class="view-switcher" role="group" aria-label="Modo de apresentação aos respondentes">
            <button
              type="button"
              :aria-pressed="versionLayoutMode === 'cards'"
              title="Exibir como cards contínuos em página única"
              @click="setVersionLayoutMode('cards')"
            >
              📄 Cards
            </button>
            <button
              type="button"
              :aria-pressed="versionLayoutMode === 'wizard'"
              title="Exibir como etapas sequenciais (Passo a Passo)"
              @click="setVersionLayoutMode('wizard')"
            >
              📋 Etapas
            </button>
          </div>

          <button
            v-if="editable"
            class="secondary-button"
            type="button"
            :disabled="state.saving"
            @click="openPresetsDrawer"
          >
            <span aria-hidden="true">⚡</span>
            Biblioteca de Presets e Blocos
          </button>

          <div class="view-switcher" role="group" aria-label="Modo de visualização do construtor">
            <button
              type="button"
              :aria-pressed="viewMode === 'split'"
              @click="viewMode = 'split'"
            >
              Dividido
            </button>
            <button
              v-if="editable"
              type="button"
              :aria-pressed="viewMode === 'edit'"
              @click="viewMode = 'edit'"
            >
              Editor
            </button>
            <button
              type="button"
              :aria-pressed="viewMode === 'preview'"
              @click="viewMode = 'preview'"
            >
              Pré-visualização
            </button>
          </div>
        </div>
      </div>

      <div v-if="state.version?.is_published" class="immutability-banner" role="status">
        <div>
          <strong>Esta versão está publicada e imutável.</strong>
          <div>Para editar a estrutura ou incluir novos campos, retorne ao formulário e clique em “Nova versão”.</div>
        </div>
        <RouterLink class="ghost-button" :to="`/forms/${formId}`">Ir para o formulário</RouterLink>
      </div>

      <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

      <div class="builder-workspace" :class="`mode-${viewMode}`">
        <!-- Coluna 1: Lista de campos atuais agrupados por seções -->
        <div v-if="viewMode !== 'preview'" class="form-card">
          <h2>Estrutura de campos</h2>
          <p class="panel-subtitle" style="margin-bottom: 1rem;">
            {{ editable ? 'Campos organizados em cards lógicos com controle de ordem e largura.' : 'Estrutura congelada desta versão.' }}
          </p>

          <div v-if="!state.fields.length" class="empty-state">
            <p>Nenhum campo cadastrado nesta versão ainda.</p>
          </div>

          <div v-else class="builder-sections-list">
            <div
              v-for="section in groupedBuilderSections"
              :key="section.id"
              class="builder-section-group"
            >
              <div class="builder-section-header">
                <h3>
                  <span>📁 {{ section.title }}</span>
                </h3>
                <span class="mono-badge">{{ section.fields.length }} campo(s)</span>
              </div>

              <ul class="fields-list">
                <li
                  v-for="field in section.fields"
                  :key="field.id"
                  class="field-row"
                >
                  <div class="field-row-main">
                    <div class="field-title-line">
                      <span class="mono-badge" style="margin-right: 0.45rem;">{{ field.order }}</span>
                      <strong>{{ field.label }}</strong>
                      <span class="layout-badge" :title="`Largura: ${widthLabels[getFieldWidth(field)] || '100%'}`">
                        {{ getFieldWidth(field) === 'half' ? '50%' : (getFieldWidth(field) === 'third' ? '33%' : '100%') }}
                      </span>
                      <span
                        v-if="getFieldCondition(field)"
                        class="condition-badge"
                        :title="formatConditionSummary(getFieldCondition(field))"
                      >
                        <span aria-hidden="true">⚡</span>
                        {{ formatConditionSummary(getFieldCondition(field)) }}
                      </span>
                    </div>
                    <span class="field-row-meta">
                      <code>{{ field.name }}</code> · {{ typeLabels[field.type] || field.type }}
                      <span v-if="field.is_required">· <strong>obrigatório</strong></span>
                    </span>
                  </div>

                  <div v-if="editable" class="field-row-actions">
                    <button
                      class="ghost-button icon-button"
                      type="button"
                      :disabled="state.saving || state.fields.findIndex(f => f.id === field.id) === 0"
                      :aria-label="`Mover campo ${field.label} para cima`"
                      title="Mover para cima"
                      @click="moveField(state.fields.findIndex(f => f.id === field.id), -1)"
                    >
                      <span aria-hidden="true">↑</span>
                    </button>
                    <button
                      class="ghost-button icon-button"
                      type="button"
                      :disabled="state.saving || state.fields.findIndex(f => f.id === field.id) === state.fields.length - 1"
                      :aria-label="`Mover campo ${field.label} para baixo`"
                      title="Mover para baixo"
                      @click="moveField(state.fields.findIndex(f => f.id === field.id), 1)"
                    >
                      <span aria-hidden="true">↓</span>
                    </button>
                    <button
                      class="ghost-button"
                      type="button"
                      :disabled="state.saving"
                      :aria-label="`Duplicar campo ${field.label}`"
                      title="Duplicar este campo"
                      @click="handleDuplicateField(field)"
                    >
                      Duplicar
                    </button>
                    <button
                      class="ghost-button"
                      type="button"
                      :disabled="state.saving"
                      :aria-label="`Editar campo ${field.label}`"
                      @click="startEditField(field)"
                    >
                      Editar
                    </button>
                    <button
                      class="ghost-button ghost-danger"
                      type="button"
                      :disabled="state.saving"
                      :aria-label="`Remover campo ${field.label}`"
                      @click="state.deletingField = field"
                    >
                      Remover
                    </button>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Coluna 2: Formulário de adição / edição de campo -->
        <div v-if="editable && viewMode !== 'preview'" class="form-card">
          <h2>{{ isEditing ? 'Editar campo' : 'Novo campo' }}</h2>
          <p class="panel-subtitle" style="margin-bottom: 1rem;">
            Configure rótulo, identificador técnico, grupo lógico e layout em colunas.
          </p>

          <form class="form" @submit.prevent="handleCreateField">
            <fieldset class="response-fields form" :disabled="state.saving">
              <div class="field">
                <label for="builder-field-label">
                  <span>Rótulo visível</span>
                  <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <input
                  id="builder-field-label"
                  v-model="newField.label"
                  type="text"
                  required
                  minlength="2"
                  maxlength="255"
                  placeholder="Ex.: Número do Benefício"
                />
              </div>

              <div class="field">
                <label for="builder-field-name">
                  <span>Nome técnico</span>
                  <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <input
                  id="builder-field-name"
                  v-model="newField.name"
                  type="text"
                  required
                  pattern="^[a-z0-9_]+$"
                  maxlength="100"
                  placeholder="ex.: numero_beneficio"
                  aria-describedby="builder-field-name-hint"
                />
                <small id="builder-field-name-hint" class="field-hint">
                  Use apenas letras minúsculas sem acento, números e sublinhado (<code>_</code>).
                </small>
              </div>

              <!-- Seção de Grupo / Card Lógico -->
              <div class="field">
                <label for="builder-field-section">
                  <span>Grupo / Card Lógico (opcional)</span>
                </label>
                <input
                  id="builder-field-section"
                  v-model="newField.section"
                  type="text"
                  list="builder-section-suggestions"
                  maxlength="100"
                  placeholder="ex.: Identificação Básica, Endereço..."
                  aria-describedby="builder-field-section-hint"
                />
                <datalist id="builder-section-suggestions">
                  <option v-for="s in existingSections" :key="s" :value="s" />
                </datalist>
                <small id="builder-field-section-hint" class="field-hint">
                  Campos com o mesmo grupo são reunidos visualmente no mesmo card.
                </small>
              </div>

              <div v-if="newField.section" class="field">
                <label for="builder-field-section-desc">
                  <span>Subtítulo do Grupo (opcional)</span>
                </label>
                <input
                  id="builder-field-section-desc"
                  v-model="newField.section_description"
                  type="text"
                  maxlength="255"
                  placeholder="ex.: Preencha seus dados de localização e residência"
                />
              </div>

              <!-- Seletor de Largura no Formulário -->
              <div class="field">
                <label for="builder-field-width">Largura no formulário (colunas)</label>
                <select id="builder-field-width" v-model="newField.width">
                  <option value="full">Largura total (100% — 12 colunas)</option>
                  <option value="half">Metade da linha (50% — 6 colunas)</option>
                  <option value="third">Um terço da linha (33% — 4 colunas)</option>
                </select>
              </div>

              <div class="field">
                <label for="builder-field-type">Tipo de resposta</label>
                <select id="builder-field-type" v-model="newField.type">
                  <option v-for="t in fieldTypes" :key="t.value" :value="t.value">
                    {{ t.label }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label for="builder-field-order">
                  <span>Ordem de exibição</span>
                  <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <input
                  id="builder-field-order"
                  v-model.number="newField.order"
                  type="number"
                  min="1"
                  max="2147483647"
                  step="1"
                  required
                />
              </div>

              <label for="builder-field-required" class="field field-inline">
                <input id="builder-field-required" v-model="newField.is_required" type="checkbox" />
                <span>Preenchimento obrigatório</span>
              </label>

              <div v-if="needsOptions" class="field">
                <label for="builder-field-options">
                  <span>Opções de escolha</span>
                  <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <textarea
                  id="builder-field-options"
                  v-model="newField.optionsText"
                  rows="4"
                  placeholder="sim|Sim&#10;nao|Não"
                  aria-describedby="builder-field-options-hint"
                />
                <small id="builder-field-options-hint" class="field-hint">
                  Informe uma opção por linha, usando <code>valor|Rótulo</code> ou apenas o texto.
                </small>
              </div>

              <div v-if="isFileField" class="field">
                <label for="builder-field-file-options">
                  <span>Formatos permitidos (opcional)</span>
                </label>
                <textarea
                  id="builder-field-file-options"
                  v-model="newField.optionsText"
                  rows="3"
                  placeholder="pdf|Documento PDF&#10;png|Imagem PNG&#10;jpg|Imagem JPG"
                  aria-describedby="builder-field-file-options-hint"
                />
                <small id="builder-field-file-options-hint" class="field-hint">
                  Informe as extensões permitidas, uma por linha (ex: <code>pdf|PDF</code> ou apenas <code>pdf</code>). Se vazio, aceita PDF, imagens e documentos padrão.
                </small>
              </div>

              <!-- Seção de Regra Condicional -->
              <div class="conditional-section-box">
                <label for="builder-field-is-conditional" class="field field-inline" style="margin-bottom: 0.5rem;">
                  <input
                    id="builder-field-is-conditional"
                    v-model="newField.is_conditional"
                    type="checkbox"
                    :disabled="!availableConditionTargets.length"
                  />
                  <span><strong>Regra de exibição condicional</strong></span>
                </label>

                <small v-if="!availableConditionTargets.length" class="field-hint">
                  Cadastre pelo menos outro campo nesta versão para habilitar condições de exibição.
                </small>

                <div v-else-if="newField.is_conditional" class="conditional-rules-grid">
                  <div class="field">
                    <label for="builder-condition-field">Quando o campo</label>
                    <select id="builder-condition-field" v-model="newField.condition_field" required>
                      <option value="" disabled>Selecione um campo de disparo…</option>
                      <option v-for="target in availableConditionTargets" :key="target.id" :value="target.name">
                        {{ target.label }} ({{ target.name }})
                      </option>
                    </select>
                  </div>

                  <div class="field">
                    <label for="builder-condition-operator">Condição</label>
                    <select id="builder-condition-operator" v-model="newField.condition_operator">
                      <option v-for="op in conditionOperators" :key="op.value" :value="op.value">
                        {{ op.label }}
                      </option>
                    </select>
                  </div>

                  <div v-if="!['filled', 'empty'].includes(newField.condition_operator)" class="field">
                    <label for="builder-condition-value">Valor esperado</label>
                    <select
                      v-if="targetFieldChoices.length"
                      id="builder-condition-value"
                      v-model="newField.condition_value"
                    >
                      <option value="">Selecione a opção esperada…</option>
                      <option v-for="choice in targetFieldChoices" :key="choice.value" :value="choice.value">
                        {{ choice.label }} ({{ choice.value }})
                      </option>
                    </select>
                    <input
                      v-else
                      id="builder-condition-value"
                      v-model="newField.condition_value"
                      type="text"
                      placeholder="ex.: sim, 10, outros"
                    />
                  </div>
                </div>
              </div>

              <div class="form-actions-inline">
                <button class="primary-button" type="submit" :disabled="state.saving">
                  {{ state.saving ? 'Salvando…' : (isEditing ? 'Salvar alterações' : 'Adicionar campo') }}
                </button>
                <button v-if="isEditing" class="ghost-button" type="button" :disabled="state.saving" @click="cancelEdit">
                  Cancelar edição
                </button>
              </div>
            </fieldset>
          </form>
        </div>

        <!-- Coluna 3: Pré-visualização Dinâmica com Grid e Cards -->
        <section
          v-if="viewMode === 'split' || viewMode === 'preview'"
          class="preview-surface"
          aria-labelledby="live-preview-heading"
        >
          <div class="card-heading">
            <h2 id="live-preview-heading">Pré-visualização do preenchimento</h2>
            <StatusBadge tone="muted" :label="versionLayoutMode === 'wizard' ? 'Modo Etapas (Wizard)' : 'Modo Cards Contínuos'" />
          </div>
          <p class="panel-subtitle" style="margin-bottom: 1.15rem;">
            Veja em tempo real como esta ficha será apresentada com o layout em colunas configurado.
          </p>

          <div v-if="!previewFields.length" class="empty-state">
            <p>Adicione campos ou comece a digitar um rótulo para visualizar a ficha aqui.</p>
          </div>

          <div v-else class="preview-layout-container">
            <!-- Simulação do Stepper no Preview caso Modo Wizard -->
            <nav v-if="versionLayoutMode === 'wizard' && totalPreviewSteps > 1" class="wizard-stepper" aria-label="Etapas da simulação">
              <button
                v-for="(sec, idx) in groupedPreviewSections"
                :key="sec.id"
                type="button"
                class="wizard-step-pill"
                :class="{ active: previewStepIndex === idx }"
                @click="previewStepIndex = idx"
              >
                <span class="wizard-step-number" aria-hidden="true">{{ idx + 1 }}</span>
                <span>{{ sec.title }}</span>
              </button>
            </nav>

            <!-- Cards no Preview -->
            <template v-if="versionLayoutMode === 'wizard' && totalPreviewSteps > 1">
              <section v-if="currentPreviewSection" class="form-section-card">
                <div class="section-card-header">
                  <div>
                    <h3 class="section-card-title">{{ currentPreviewSection.title }}</h3>
                    <p v-if="currentPreviewSection.description" class="section-card-desc">
                      {{ currentPreviewSection.description }}
                    </p>
                  </div>
                  <span class="mono-badge">Etapa {{ previewStepIndex + 1 }} de {{ totalPreviewSteps }}</span>
                </div>

                <div class="form-grid">
                  <div
                    v-for="field in currentPreviewSection.fields"
                    :key="`preview-${field.id}`"
                    :class="getFieldWidthClass(field)"
                  >
                    <FormResponseField
                      v-model="previewValues[field.name]"
                      :field="field"
                      id-prefix="preview"
                    />
                  </div>
                </div>
              </section>
            </template>

            <template v-else>
              <section
                v-for="sec in groupedPreviewSections"
                :key="sec.id"
                class="form-section-card"
              >
                <div v-if="!sec.isDefault || groupedPreviewSections.length > 1" class="section-card-header">
                  <div>
                    <h3 class="section-card-title">{{ sec.title }}</h3>
                    <p v-if="sec.description" class="section-card-desc">{{ sec.description }}</p>
                  </div>
                  <span class="mono-badge">{{ sec.fields.length }} campo(s)</span>
                </div>

                <div class="form-grid">
                  <div
                    v-for="field in sec.fields"
                    :key="`preview-${field.id}`"
                    :class="getFieldWidthClass(field)"
                  >
                    <FormResponseField
                      v-model="previewValues[field.name]"
                      :field="field"
                      id-prefix="preview"
                    />
                  </div>
                </div>
              </section>
            </template>
          </div>
        </section>
      </div>

      <!-- Slide-over Drawer: Biblioteca de Presets e Blocos -->
      <div
        v-if="drawerOpen"
        class="drawer-backdrop"
        @click="closePresetsDrawer"
      >
        <aside
          class="drawer-panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="drawer-title"
          @click.stop
          @keydown.escape="closePresetsDrawer"
        >
          <div class="drawer-header">
            <div>
              <h2 id="drawer-title" class="drawer-title">Biblioteca de Presets e Blocos</h2>
              <p class="drawer-subtitle">
                Acelere a construção do formulário com campos brasileiros padronizados e seções prontas.
              </p>
            </div>
            <button
              class="ghost-button icon-button"
              type="button"
              aria-label="Fechar gaveta de presets"
              @click="closePresetsDrawer"
            >
              <span aria-hidden="true">✕</span>
            </button>
          </div>

          <div class="drawer-search">
            <label for="drawer-search-input" class="sr-only">Pesquisar presets e blocos</label>
            <input
              id="drawer-search-input"
              v-model="presetSearch"
              type="search"
              placeholder="Buscar por CPF, CEP, endereço, satisfação…"
            />
            <div class="drawer-tabs" role="tablist" aria-label="Filtrar por tipo de preset">
              <button
                type="button"
                role="tab"
                :aria-selected="presetFilterTab === 'all'"
                :class="{ active: presetFilterTab === 'all' }"
                @click="presetFilterTab = 'all'"
              >
                Todos
              </button>
              <button
                type="button"
                role="tab"
                :aria-selected="presetFilterTab === 'blocks'"
                :class="{ active: presetFilterTab === 'blocks' }"
                @click="presetFilterTab = 'blocks'"
              >
                Blocos Prontos
              </button>
              <button
                type="button"
                role="tab"
                :aria-selected="presetFilterTab === 'presets'"
                :class="{ active: presetFilterTab === 'presets' }"
                @click="presetFilterTab = 'presets'"
              >
                Campos Individuais
              </button>
            </div>
          </div>

          <div class="drawer-body">
            <!-- Seção: Blocos Prontos -->
            <section
              v-if="presetFilterTab === 'all' || presetFilterTab === 'blocks'"
              class="drawer-section"
              aria-labelledby="heading-blocks"
            >
              <div class="card-heading" style="margin-bottom: 0.35rem;">
                <h3 id="heading-blocks" class="drawer-section-heading">Blocos Prontos (Inserção em Grupo)</h3>
                <span class="mono-badge">{{ filteredBlockPresets.length }}</span>
              </div>
              <p class="drawer-section-hint">
                Adiciona múltiplos campos configurados sequencialmente com apenas 1 clique.
              </p>

              <div class="drawer-grid">
                <article
                  v-for="block in filteredBlockPresets"
                  :key="block.id"
                  class="preset-card block-card"
                >
                  <div class="preset-card-header">
                    <div>
                      <h4 class="preset-card-title">{{ block.name }}</h4>
                      <span class="preset-badge">{{ block.badge }}</span>
                    </div>
                  </div>
                  <p class="preset-card-desc">{{ block.description }}</p>
                  <div class="preset-card-preview-tags">
                    <span v-for="f in block.fields" :key="f.name" class="preset-field-tag">
                      {{ f.label }} ({{ f.width === 'half' ? '50%' : (f.width === 'third' ? '33%' : '100%') }})
                    </span>
                  </div>
                  <div class="preset-card-actions">
                    <button
                      class="primary-button btn-sm"
                      type="button"
                      :disabled="state.saving || insertingBlock"
                      @click="insertBlockDirectly(block)"
                    >
                      {{ insertingBlock ? 'Adicionando…' : `+ Inserir Bloco (${block.fields.length} campos)` }}
                    </button>
                  </div>
                </article>
              </div>
            </section>

            <!-- Seção: Campos Individuais -->
            <section
              v-if="presetFilterTab === 'all' || presetFilterTab === 'presets'"
              class="drawer-section"
              aria-labelledby="heading-presets"
            >
              <div class="card-heading" style="margin-bottom: 0.35rem;">
                <h3 id="heading-presets" class="drawer-section-heading">Campos Individuais Especializados</h3>
                <span class="mono-badge">{{ filteredFieldPresets.length }}</span>
              </div>
              <p class="drawer-section-hint">
                Campos prontos para uso com identificadores limpos e tipos corretos.
              </p>

              <div class="drawer-grid">
                <article
                  v-for="preset in filteredFieldPresets"
                  :key="preset.id"
                  class="preset-card"
                >
                  <div class="preset-card-header">
                    <div>
                      <h4 class="preset-card-title">{{ preset.label }}</h4>
                      <span class="preset-badge">{{ preset.badge }}</span>
                    </div>
                    <code class="preset-slug">{{ preset.name }}</code>
                  </div>
                  <p class="preset-card-desc">{{ preset.description }}</p>
                  <div class="preset-card-actions">
                    <button
                      class="primary-button btn-sm"
                      type="button"
                      :disabled="state.saving"
                      title="Inserir diretamente na versão"
                      @click="insertPresetDirectly(preset)"
                    >
                      + Inserir
                    </button>
                    <button
                      class="ghost-button btn-sm"
                      type="button"
                      :disabled="state.saving"
                      title="Carregar no editor para ajustar antes de salvar"
                      @click="applyPresetToEditor(preset)"
                    >
                      Carregar no editor
                    </button>
                  </div>
                </article>
              </div>
            </section>
          </div>
        </aside>
      </div>

      <ConfirmDialog
        :open="!!state.deletingField"
        dialog-id="delete-field-confirm"
        title="Remover este campo?"
        :description="state.deletingField ? `O campo “${state.deletingField.label}” (${state.deletingField.name}) será removido da versão em edição.` : ''"
        :confirm-label="state.saving ? 'Removendo…' : 'Remover campo'"
        tone="danger"
        :busy="state.saving"
        @confirm="confirmDeleteField"
        @cancel="state.deletingField = null"
      />
    </template>

    <section v-else class="surface-card">
      <p class="feedback error" role="alert">{{ state.error }}</p>
      <button class="ghost-button" type="button" @click="loadData">Tentar novamente</button>
    </section>
  </div>
</template>

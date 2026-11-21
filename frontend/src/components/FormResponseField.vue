<script setup>
import { computed, ref } from 'vue'
import { getFieldChoices } from '../utils/conditionalLogic.js'

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: '' },
  error: { type: String, default: '' },
  idPrefix: { type: String, default: 'response' },
  uploading: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'file-selected'])

const value = computed({
  get: () => props.modelValue,
  set: next => emit('update:modelValue', next),
})
const id = computed(() => `${props.idPrefix}-${props.field.id}`)
const isDragging = ref(false)

const isFile = computed(() => props.field.type === 'file')
const choices = computed(() => getFieldChoices(props.field))

const acceptExtensions = computed(() => {
  if (!choices.value.length) {
    return '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.txt'
  }
  return choices.value
    .map(opt => (opt.value ? `.${opt.value.trim().replace(/^\./, '').toLowerCase()}` : ''))
    .filter(Boolean)
    .join(',')
})

const acceptedFormatsLabel = computed(() => {
  if (!choices.value.length) {
    return 'PDF, PNG, JPG, DOCX, XLSX'
  }
  return choices.value
    .map(opt => (opt.label || opt.value || '').toUpperCase())
    .filter(Boolean)
    .join(', ')
})

const fileName = computed(() => {
  if (!value.value) return ''
  if (typeof value.value === 'object') return value.value.name || 'Arquivo anexado'
  return String(value.value).split('/').pop()
})

const fileSizeLabel = computed(() => {
  const bytes = typeof value.value === 'object' ? value.value.size : null
  if (!bytes || isNaN(bytes)) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
})

function handleFile(file) {
  if (!file) return
  emit('file-selected', { file, field: props.field })
}

function onFileInputChange(event) {
  const file = event.target.files?.[0]
  if (file) handleFile(file)
}

function onDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) handleFile(file)
}

function clearFile() {
  value.value = null
}
</script>

<template>
  <div class="field" :data-field-name="field.name">
    <!-- Non-choice fields (text, textarea, number, email, date, file) -->
    <template v-if="!['radio', 'checkbox'].includes(field.type)">
      <label :for="id">
        <span>{{ field.label }}</span>
        <span v-if="field.is_required" class="required-indicator" aria-hidden="true">*</span>
        <small v-if="field.is_required"> (obrigatório)</small>
      </label>

      <!-- Textarea -->
      <textarea
        v-if="field.type === 'textarea'"
        :id="id"
        v-model="value"
        rows="4"
        :required="field.is_required"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${id}-error` : undefined"
      />

      <!-- Select -->
      <select
        v-else-if="field.type === 'select'"
        :id="id"
        v-model="value"
        :required="field.is_required"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${id}-error` : undefined"
      >
        <option value="">Selecione uma opção…</option>
        <option v-for="option in choices" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>

      <!-- File Attachment Input -->
      <div
        v-else-if="isFile"
        class="attachment-control"
        :class="{ 'is-dragging': isDragging, 'has-file': Boolean(value) }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
      >
        <!-- Native Accessible Input with Label association -->
        <input
          :id="id"
          type="file"
          class="file-input-native"
          :accept="acceptExtensions"
          :required="field.is_required && !value"
          :aria-invalid="!!error"
          :aria-describedby="error ? `${id}-error` : `${id}-hint`"
          @change="onFileInputChange"
        />

        <!-- File selected card -->
        <div v-if="value" class="attachment-card">
          <div class="attachment-card-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
          </div>
          <div class="attachment-card-meta">
            <strong class="attachment-card-name">{{ fileName }}</strong>
            <span v-if="fileSizeLabel" class="attachment-card-size">{{ fileSizeLabel }}</span>
            <span v-if="uploading" class="attachment-uploading-tag">Enviando…</span>
          </div>
          <div class="attachment-card-actions">
            <button
              type="button"
              class="attachment-remove-btn"
              :aria-label="`Remover anexo ${fileName}`"
              @click="clearFile"
            >
              Remover
            </button>
          </div>
        </div>

        <!-- Dropzone prompt when no file attached -->
        <label v-else :for="id" class="attachment-dropzone">
          <div class="attachment-dropzone-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
          </div>
          <div class="attachment-dropzone-text">
            <span class="attachment-dropzone-title">Clique para selecionar ou arraste o arquivo</span>
            <span :id="`${id}-hint`" class="attachment-dropzone-formats">Formatos permitidos: {{ acceptedFormatsLabel }} (máx. 10 MB)</span>
          </div>
        </label>
      </div>

      <!-- Standard Inputs (text, number, email, date) -->
      <input
        v-else
        :id="id"
        v-model="value"
        :type="field.type"
        :step="field.type === 'number' ? 'any' : undefined"
        :required="field.is_required"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${id}-error` : undefined"
      />
    </template>

    <!-- Choice fields (radio, checkbox) -->
    <fieldset
      v-else
      class="response-options"
      :aria-describedby="error ? `${id}-error` : undefined"
      :aria-invalid="!!error"
    >
      <legend>
        <span>{{ field.label }}</span>
        <span v-if="field.is_required" class="required-indicator" aria-hidden="true">*</span>
        <small v-if="field.is_required"> (obrigatório)</small>
      </legend>
      <div class="field-options">
        <label
          v-for="(option, idx) in choices"
          :key="option.value"
          :for="`${id}-opt-${idx}`"
          class="field-option"
        >
          <input
            :id="`${id}-opt-${idx}`"
            v-model="value"
            :type="field.type"
            :name="id"
            :value="option.value"
          />
          <span>{{ option.label }}</span>
        </label>
      </div>
    </fieldset>

    <span v-if="error" :id="`${id}-error`" class="field-error" role="alert">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="8" cy="8" r="6.25" />
        <path d="M8 4.75v3.75M8 11.25h.01" stroke-linecap="round" />
      </svg>
      <span>{{ error }}</span>
    </span>
  </div>
</template>

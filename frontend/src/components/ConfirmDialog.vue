<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  open: Boolean,
  title: { type: String, required: true },
  description: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirmar' },
  cancelLabel: { type: String, default: 'Cancelar' },
  tone: { type: String, default: 'primary' },
  busy: Boolean,
  confirmDisabled: Boolean,
  dialogId: { type: String, default: 'app-modal' },
})

const emit = defineEmits(['confirm', 'cancel'])
const dialogRef = ref(null)
const cancelBtnRef = ref(null)
let previousActive = null

function getFocusableElements() {
  if (!dialogRef.value) return []
  return [...dialogRef.value.querySelectorAll(
    'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
  )]
}

function handleKeydown(event) {
  if (!props.open) return
  if (event.key === 'Escape') {
    if (!props.busy) {
      event.preventDefault()
      emit('cancel')
    }
    return
  }
  if (event.key !== 'Tab') return
  const focusable = getFocusableElements()
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  const active = typeof document !== 'undefined' ? document.activeElement : null
  if (event.shiftKey && (active === first || !dialogRef.value.contains(active))) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

function restoreFocus() {
  const target = previousActive
  previousActive = null
  if (target && typeof target.focus === 'function') {
    nextTick(() => target.focus())
  }
}

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    if (typeof document !== 'undefined') {
      previousActive = document.activeElement
    }
    await nextTick()
    const focusable = getFocusableElements()
    ;(cancelBtnRef.value || focusable[0] || dialogRef.value)?.focus?.()
  } else {
    restoreFocus()
  }
}, { immediate: true })

onBeforeUnmount(() => {
  if (props.open) restoreFocus()
})

function onBackdropClick(event) {
  if (event.target === event.currentTarget && !props.busy) {
    emit('cancel')
  }
}
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click="onBackdropClick" @keydown="handleKeydown">
    <div
      ref="dialogRef"
      class="modal-dialog"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`${dialogId}-title`"
      :aria-describedby="description ? `${dialogId}-desc` : undefined"
      tabindex="-1"
    >
      <div class="modal-header">
        <h2 :id="`${dialogId}-title`">{{ title }}</h2>
      </div>
      <div class="modal-body">
        <p v-if="description" :id="`${dialogId}-desc`">{{ description }}</p>
        <slot />
      </div>
      <div class="modal-actions">
        <button
          ref="cancelBtnRef"
          type="button"
          class="ghost-button"
          :disabled="busy"
          @click="emit('cancel')"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          :class="tone === 'danger' ? 'danger-button' : 'primary-button'"
          :disabled="busy || confirmDisabled"
          @click="emit('confirm')"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

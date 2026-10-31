import { readonly, ref } from 'vue'
import { announce } from './useBreadcrumbs.js'

const items = ref([])
let nextId = 1

export const toasts = readonly(items)

export function dismissToast(id) {
  items.value = items.value.filter(item => item.id !== id)
}

export function pushToast(message, tone = 'success', durationMs = 4500) {
  if (!message) return
  const id = nextId++
  items.value = [...items.value.slice(-3), { id, message, tone }]
  announce(message)
  if (durationMs > 0 && typeof setTimeout === 'function') {
    setTimeout(() => dismissToast(id), durationMs)
  }
  return id
}

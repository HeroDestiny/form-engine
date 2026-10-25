import { readonly, ref } from 'vue'

const STORAGE_KEY = 'form_engine_theme'
const VALID_MODES = ['system', 'light', 'dark']

function readStoredTheme() {
  try {
    if (typeof localStorage === 'undefined') return 'system'
    const stored = localStorage.getItem(STORAGE_KEY)
    return VALID_MODES.includes(stored) ? stored : 'system'
  } catch {
    return 'system'
  }
}

function applyDomTheme(mode) {
  if (typeof document === 'undefined' || !document.documentElement) return
  if (mode === 'light' || mode === 'dark') {
    document.documentElement.setAttribute('data-theme', mode)
  } else {
    document.documentElement.removeAttribute('data-theme')
  }
}

const currentMode = ref(readStoredTheme())
applyDomTheme(currentMode.value)

export const themeMode = readonly(currentMode)

export function setTheme(mode) {
  const next = VALID_MODES.includes(mode) ? mode : 'system'
  currentMode.value = next
  try {
    if (typeof localStorage !== 'undefined') {
      if (next === 'system') localStorage.removeItem(STORAGE_KEY)
      else localStorage.setItem(STORAGE_KEY, next)
    }
  } catch {
    // Ignore storage quota or privacy mode errors
  }
  applyDomTheme(next)
}

export function cycleTheme() {
  const order = ['system', 'light', 'dark']
  const nextIndex = (order.indexOf(currentMode.value) + 1) % order.length
  setTheme(order[nextIndex])
}

export function initialsFromName(name = '') {
  const parts = String(name).trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return 'U'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
}

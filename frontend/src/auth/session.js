import { computed, reactive, readonly } from 'vue'
import { getCurrentUser, login, logout } from '../api.js'
import { clearAuthToken, getAuthToken, onTokenChange } from './token.js'
import { adminRoles, hasRole, managementRoles } from './permissions.js'

const state = reactive({ user: null, tenant: null, error: '' })
export const session = readonly(state)
export const canManageForms = computed(() => hasRole(state.user, managementRoles))
export const canAdminister = computed(() => hasRole(state.user, adminRoles))
export const canManageTenants = computed(() => state.user?.role === 'admin-sistema')
let restoredToken = ''
let pending = null
let generation = 0

onTokenChange(() => {
  generation++
  restoredToken = ''
  pending = null
  state.user = null
  state.tenant = null
  state.error = ''
})

export async function restoreSession() {
  const token = getAuthToken()
  if (!token) return null
  if (restoredToken === token && state.user) return state.user
  if (pending?.token === token) return pending.promise

  const currentGeneration = generation
  const promise = (async () => {
    try {
      const response = await getCurrentUser()
      if (getAuthToken() !== token || generation !== currentGeneration) return null
      if (!response?.data?.user) {
        clearAuthToken('expired', token)
        return null
      }
      state.user = response.data.user
      state.tenant = response.data.tenant ?? null
      state.error = ''
      restoredToken = token
      return state.user
    } catch (error) {
      if (getAuthToken() !== token || generation !== currentGeneration) return null
      state.error = error.status ? error.message : 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.'
      throw error
    } finally {
      if (generation === currentGeneration) pending = null
    }
  })()
  pending = { token, promise }
  return promise
}

export async function signIn(credentials) {
  await login(credentials)
  return restoreSession()
}

export async function signOut() {
  await logout()
}

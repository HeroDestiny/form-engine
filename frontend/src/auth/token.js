const TOKEN_KEY = 'fe_token'
const listeners = new Set()

export function getAuthToken() {
  return window.localStorage.getItem(TOKEN_KEY) ?? ''
}

export function onTokenChange(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

function notify(reason) {
  for (const listener of listeners) listener(reason)
}

export function setAuthToken(token) {
  window.localStorage.setItem(TOKEN_KEY, token)
  notify('login')
}

export function clearAuthToken(reason = 'logout', expectedToken = getAuthToken()) {
  // A delayed response from an old session must not invalidate a new login.
  if (!expectedToken || getAuthToken() !== expectedToken) return
  window.localStorage.removeItem(TOKEN_KEY)
  notify(reason)
}

export function syncAuthToken(event) {
  if (event.storageArea === window.localStorage && (event.key === TOKEN_KEY || event.key === null)) {
    notify('storage')
  }
}

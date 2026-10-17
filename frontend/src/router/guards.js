import { restoreSession } from '../auth/session.js'
import { getAuthToken } from '../auth/token.js'
import { hasRole, safeReturnPath } from '../auth/permissions.js'

export async function guardRoute(to) {
  if (to.meta.public && to.name !== 'login') return true
  if (!getAuthToken()) {
    return to.meta.public ? true : { name: 'login', query: { redirect: to.fullPath } }
  }

  let user
  try {
    user = await restoreSession()
  } catch {
    return { name: 'session-error', query: { redirect: safeReturnPath(to.fullPath) } }
  }
  if (!user) {
    return to.meta.public ? true : { name: 'login', query: { redirect: to.fullPath, reason: 'expired' } }
  }
  if (to.name === 'login') return safeReturnPath(to.query.redirect)
  if (to.meta.roles && !hasRole(user, to.meta.roles)) return { name: 'forbidden' }
  return true
}

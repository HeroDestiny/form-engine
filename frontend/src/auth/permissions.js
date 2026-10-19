export const managementRoles = ['manager', 'admin', 'admin-sistema']
export const adminRoles = ['admin', 'admin-sistema']

export function hasRole(user, roles) {
  return !!user && roles.includes(user.role)
}

export function safeReturnPath(value) {
  // Only allow internal application paths; avoid login/guard redirect loops.
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /[\\\s]/.test(value)) return '/'
  const path = value.split(/[?#]/)[0].replace(/\/+$/, '').toLowerCase()
  if (['/login', '/session-error', '/forbidden'].includes(path)) return '/'
  return value
}

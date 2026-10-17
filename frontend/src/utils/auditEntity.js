export function auditEntityPath(payload) {
  if (!payload?.entity_exists) return null
  const { entity_type: type, entity_id: id } = payload.log ?? {}
  if (!Number.isInteger(Number(id)) || Number(id) < 1) return null
  if (type === 'form') return `/forms/${id}`
  if (type === 'form_submission') return `/submissions/${id}`
  // These records do not expose the parent form; avoid guessing a destination.
  return null
}

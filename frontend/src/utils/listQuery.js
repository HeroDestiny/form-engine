export function decodeListQuery(query, defaults, paginated = true) {
  const filters = { ...defaults }
  for (const key of Object.keys(defaults)) {
    const value = query[key]
    if (typeof value !== 'string' || value.length > 255) continue
    if (key.endsWith('_id') && value && !/^[1-9]\d{0,9}$/.test(value)) continue
    if (key.endsWith('_id') && Number(value) > 2147483647) continue
    const enums = { status: ['draft', 'submitted'], sort: ['name', 'created_at'], order: ['asc', 'desc'] }
    if (enums[key] && !enums[key].includes(value)) continue
    if (key.startsWith('date_') && value && !/^\d{4}-\d{2}-\d{2}$/.test(value)) continue
    filters[key] = value
  }
  const page = paginated && typeof query.page === 'string' && /^[1-9]\d{0,9}$/.test(query.page) && Number(query.page) <= 2147483647 ? Number(query.page) : 1
  const perPage = paginated && ['10', '20', '50', '100'].includes(query.per_page) ? Number(query.per_page) : 20
  return { filters, page, perPage }
}

export function encodeListQuery(filters, defaults, page = 1, perPage = 20, paginated = true) {
  const query = {}
  for (const key of Object.keys(defaults)) {
    const value = typeof filters[key] === 'string' ? filters[key].trim() : filters[key]
    if (value !== '' && value !== undefined && value !== null && value !== defaults[key]) query[key] = String(value)
  }
  if (paginated && page > 1) query.page = String(page)
  if (paginated && perPage !== 20) query.per_page = String(perPage)
  return query
}

export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('pt-BR')
}

export function versionStatus(version) {
  return version?.is_published ? 'Versão publicada' : version ? 'Versão em rascunho' : 'Sem versão'
}

export function buildExportFilters(filters) {
  if (!filters.form_id) throw new Error('Selecione e aplique um formulário para exportar.')
  if (filters.status === 'draft') throw new Error('A exportação CSV inclui somente submissões enviadas. Altere o filtro de status.')
  if (filters.user_id) throw new Error('A exportação CSV não suporta filtro por usuário. Limpe o filtro antes de exportar.')
  const versions = filters.version_ids ?? (filters.version_id ? [filters.version_id] : [])
  return {
    form_id: Number(filters.form_id),
    ...(versions.length ? { version_ids: [...new Set(versions.map(Number))] } : {}),
    ...(filters.date_from ? { date_from: filters.date_from } : {}),
    ...(filters.date_to ? { date_to: filters.date_to } : {}),
    ...(filters.separator ? { separator: filters.separator } : {}),
  }
}

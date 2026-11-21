/**
 * Utilitários para Layout em Grade e Agrupamento Lógico de Campos (Cards e Wizard)
 */

function parseOptionsSafe(opts) {
  if (!opts) return null
  if (typeof opts === 'object') return opts
  if (typeof opts === 'string') {
    try {
      return JSON.parse(opts)
    } catch (_) {
      return null
    }
  }
  return null
}

export function getFieldSection(field) {
  if (!field || typeof field !== 'object') return null
  if (typeof field.section === 'string' && field.section.trim()) {
    return field.section.trim()
  }
  const opts = parseOptionsSafe(field.options)
  if (opts && typeof opts.section === 'string' && opts.section.trim()) {
    return opts.section.trim()
  }
  return null
}

export function getFieldSectionDescription(field) {
  if (!field || typeof field !== 'object') return null
  if (typeof field.section_description === 'string' && field.section_description.trim()) {
    return field.section_description.trim()
  }
  const opts = parseOptionsSafe(field.options)
  if (opts && typeof opts.section_description === 'string' && opts.section_description.trim()) {
    return opts.section_description.trim()
  }
  return null
}

export function getFieldWidth(fieldOrWidth) {
  if (!fieldOrWidth) return 'full'
  if (typeof fieldOrWidth === 'string') {
    return ['full', 'half', 'third'].includes(fieldOrWidth) ? fieldOrWidth : 'full'
  }
  if (typeof fieldOrWidth !== 'object') {
    return 'full'
  }
  const opts = parseOptionsSafe(fieldOrWidth.options)
  const val = fieldOrWidth.width || (opts && typeof opts === 'object' ? opts.width : null)
  if (['full', 'half', 'third'].includes(val)) {
    return val
  }
  return 'full'
}

export function getFormLayoutMode(fields = []) {
  for (const field of fields) {
    if (!field || typeof field !== 'object') continue
    const opts = parseOptionsSafe(field.options)
    const mode = field.layout_mode || (opts && typeof opts === 'object' ? opts.layout_mode : null)
    if (mode === 'wizard' || mode === 'cards') {
      return mode
    }
  }
  return 'cards'
}

export function getFieldWidthClass(fieldOrWidth) {
  const width = getFieldWidth(fieldOrWidth)
  switch (width) {
    case 'half':
      return 'col-6'
    case 'third':
      return 'col-4'
    case 'full':
    default:
      return 'col-12'
  }
}

export function groupFieldsBySection(fields = []) {
  const sectionsMap = new Map()
  const defaultSectionTitle = 'Campos Gerais'

  for (const field of fields) {
    const rawSection = getFieldSection(field)
    const sectionTitle = rawSection || defaultSectionTitle
    const sectionDesc = getFieldSectionDescription(field) || ''

    if (!sectionsMap.has(sectionTitle)) {
      const slug = sectionTitle
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9_]/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '') || 'geral'

      sectionsMap.set(sectionTitle, {
        id: `sec-${slug}`,
        title: sectionTitle,
        description: sectionDesc,
        isDefault: !rawSection,
        fields: [],
      })
    }

    const sec = sectionsMap.get(sectionTitle)
    if (!sec.description && sectionDesc) {
      sec.description = sectionDesc
    }
    sec.fields.push(field)
  }

  return Array.from(sectionsMap.values())
}

export function extractUniqueSections(fields = []) {
  const set = new Set()
  for (const f of fields) {
    const sec = getFieldSection(f)
    if (sec) set.add(sec)
  }
  return Array.from(set).sort()
}

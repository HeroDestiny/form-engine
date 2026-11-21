import { encodeFieldOptions, getFieldChoices, getFieldCondition } from './conditionalLogic.js'

export function sortedFields(fields) {
  return [...fields].sort((a, b) => a.order - b.order || a.id - b.id)
}

export function initialValues(fields, saved = []) {
  const stored = new Map(saved.map(item => [item.name, item.value]))
  return Object.fromEntries(fields.map(field => {
    const value = stored.get(field.name)
    if (field.type === 'checkbox') {
      return [field.name, Array.isArray(value) ? [...value] : []]
    }
    if (field.type === 'file') {
      return [field.name, value ?? null]
    }
    return [field.name, value ?? '']
  }))
}

export function isFieldFilled(value) {
  if (value === undefined || value === null || value === '') return false
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'object') {
    return Boolean(value.path || value.name || value.id)
  }
  return typeof value === 'string' ? value.trim() !== '' : true
}

export function submissionValues(fields, values) {
  return Object.fromEntries(fields.filter(field => {
    const value = values[field.name]
    return value !== undefined && value !== null && value !== '' && (!Array.isArray(value) || value.length > 0)
  }).map(field => [field.name, Array.isArray(values[field.name]) ? [...values[field.name]] : values[field.name]]))
}

export function formFillProgress(fields = [], values = {}) {
  const total = fields.length
  const filled = fields.filter(field => isFieldFilled(values[field.name])).length
  const requiredFields = fields.filter(field => field.is_required)
  const requiredTotal = requiredFields.length
  const requiredFilled = requiredFields.filter(field => isFieldFilled(values[field.name])).length
  const percent = requiredTotal > 0
    ? Math.round((requiredFilled / requiredTotal) * 100)
    : total > 0 ? Math.round((filled / total) * 100) : 0
  const label = requiredTotal > 0
    ? `${requiredFilled} de ${requiredTotal} campo(s) obrigatório(s) preenchido(s)`
    : `${filled} de ${total} campo(s) preenchido(s)`
  return { total, filled, requiredTotal, requiredFilled, percent, label }
}

export function fieldValidationErrors(error) {
  const result = Object.create(null)
  for (const [key, messages] of Object.entries(error?.body?.errors ?? {})) {
    const name = key.startsWith('values.') ? key.slice(7).split('.')[0] : key
    if (!result[name]) result[name] = Array.isArray(messages) ? messages[0] : String(messages)
  }
  return result
}

export function nextFieldOrder(fields) {
  return Math.max(0, ...fields.map(field => Number(field.order) || 0)) + 1
}

export function reorderFieldPair(fields, index, direction) {
  const sorted = sortedFields(fields)
  const targetIndex = index + direction
  if (index < 0 || index >= sorted.length || targetIndex < 0 || targetIndex >= sorted.length) {
    return null
  }
  const current = sorted[index]
  const target = sorted[targetIndex]
  const sameOrder = Number(current.order) === Number(target.order)
  const currentNextOrder = sameOrder ? targetIndex + 1 : Number(target.order)
  const targetNextOrder = sameOrder ? index + 1 : Number(current.order)
  return { current, target, currentNextOrder, targetNextOrder }
}

export function parseChoiceOptions(optionsText = '') {
  return String(optionsText).split('\n').map(line => line.trim()).filter(Boolean).map(line => {
    const separator = line.indexOf('|')
    const value = (separator < 0 ? line : line.slice(0, separator)).trim()
    const label = (separator < 0 ? line : line.slice(separator + 1)).trim()
    return { value, label: label || value }
  }).filter(option => option.value)
}

export function previewFieldFromDraft(field, fallbackId = 'draft-preview') {
  const label = String(field?.label ?? '').trim()
  if (!label) return null
  const needsOptions = ['select', 'radio', 'checkbox'].includes(field.type)
  const isFile = field.type === 'file'
  const condition = field.condition && field.condition.field ? field.condition : null
  const choices = needsOptions
    ? parseChoiceOptions(field.optionsText)
    : (isFile && field.optionsText ? parseChoiceOptions(field.optionsText) : [])
  const section = field.section?.trim() || null
  const section_description = field.section_description?.trim() || null
  const width = ['full', 'half', 'third'].includes(field.width) ? field.width : 'full'
  const layout_mode = ['cards', 'wizard'].includes(field.layout_mode) ? field.layout_mode : null

  const baseOptions = encodeFieldOptions(choices, condition)
  let optionsObj = null
  const hasMetadata = Boolean(section || section_description || (width && width !== 'full') || layout_mode)
  if (hasMetadata) {
    if (baseOptions && typeof baseOptions === 'object' && !Array.isArray(baseOptions)) {
      optionsObj = { ...baseOptions }
    } else if (Array.isArray(baseOptions)) {
      optionsObj = { choices: baseOptions }
    } else {
      optionsObj = {}
    }
    if (section) optionsObj.section = section
    if (section_description) optionsObj.section_description = section_description
    if (width && width !== 'full') optionsObj.width = width
    if (layout_mode) optionsObj.layout_mode = layout_mode
  } else {
    optionsObj = baseOptions
  }

  return {
    id: field.id ?? fallbackId,
    label,
    name: String(field?.name ?? '').trim() || 'campo_em_edicao',
    type: field.type || 'text',
    is_required: !!field.is_required,
    order: Number(field.order) || 1,
    options: optionsObj,
    section,
    section_description,
    width,
    layout_mode,
    ...(condition ? { condition } : {}),
  }
}

export function fieldPayload(field) {
  const payload = { label: field.label.trim(), name: field.name.trim(), type: field.type, is_required: !!field.is_required, order: Number(field.order) }
  let options = null
  if (['select', 'radio', 'checkbox'].includes(field.type)) {
    options = field.optionsText.split('\n').map(line => line.trim()).filter(Boolean).map(line => {
      const separator = line.indexOf('|')
      const value = (separator < 0 ? line : line.slice(0, separator)).trim()
      const label = (separator < 0 ? line : line.slice(separator + 1)).trim()
      return { value, label: label || value }
    })
    if (!options.length || options.some(option => !option.value)) throw new Error('Informe pelo menos uma opção com valor e rótulo.')
    if (new Set(options.map(option => option.value)).size !== options.length) throw new Error('Cada opção deve ter um valor diferente.')
  } else if (field.type === 'file' && field.optionsText) {
    options = field.optionsText.split('\n').map(line => line.trim()).filter(Boolean).map(line => {
      const separator = line.indexOf('|')
      const value = (separator < 0 ? line : line.slice(0, separator)).trim().toLowerCase()
      const label = (separator < 0 ? line : line.slice(separator + 1)).trim()
      return { value, label: label || value }
    }).filter(opt => opt.value)
  }

  if (options && options.length) {
    payload.options = options
  }

  if (field.condition && field.condition.field) {
    payload.condition = {
      field: String(field.condition.field).trim(),
      operator: String(field.condition.operator || 'equals').trim(),
      value: field.condition.value !== undefined && field.condition.value !== null ? String(field.condition.value).trim() : '',
    }
  }

  if (field.section && String(field.section).trim()) {
    payload.section = String(field.section).trim()
  }
  if (field.section_description && String(field.section_description).trim()) {
    payload.section_description = String(field.section_description).trim()
  }
  if (field.width && ['full', 'half', 'third'].includes(field.width)) {
    payload.width = field.width
  }
  if (field.layout_mode && ['cards', 'wizard'].includes(field.layout_mode)) {
    payload.layout_mode = field.layout_mode
  }

  return payload
}

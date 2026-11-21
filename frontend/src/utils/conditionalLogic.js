/**
 * Motor de Avaliação e Manipulação de Lógica Condicional para Formulários
 */

export function getFieldCondition(field) {
  if (!field) return null
  if (field.condition && typeof field.condition === 'object' && field.condition.field) {
    return field.condition
  }
  const opts = field.options
  if (opts && typeof opts === 'object') {
    if (opts.condition && typeof opts.condition === 'object' && opts.condition.field) {
      return opts.condition
    }
    if (opts.__condition__ && typeof opts.__condition__ === 'object' && opts.__condition__.field) {
      return opts.__condition__
    }
  }
  return null
}

export function getFieldChoices(field) {
  if (!field || !field.options) return []
  if (Array.isArray(field.options)) {
    return field.options.filter(opt => opt && typeof opt === 'object' && opt.value !== undefined)
  }
  if (typeof field.options === 'object' && Array.isArray(field.options.choices)) {
    return field.options.choices
  }
  return []
}

export function encodeFieldOptions(choices = [], condition = null) {
  const cleanChoices = Array.isArray(choices) ? choices.filter(c => c && c.value) : []
  const hasCondition = Boolean(condition && condition.field && condition.operator)

  if (!hasCondition) {
    return cleanChoices.length > 0 ? cleanChoices : null
  }

  return {
    choices: cleanChoices,
    condition: {
      field: String(condition.field).trim(),
      operator: String(condition.operator).trim(),
      value: condition.value !== undefined && condition.value !== null ? String(condition.value) : '',
    },
  }
}

export function evaluateCondition(condition, values = {}, allFields = [], visited = new Set()) {
  if (!condition || !condition.field) return true

  const targetName = String(condition.field).trim()
  if (!targetName) return true

  // Proteção contra ciclos de dependência
  if (visited.has(targetName)) return true
  visited.add(targetName)

  // Verifica se o campo alvo também tem uma condição que precisa estar visível
  const targetField = allFields.find(f => f.name === targetName)
  if (targetField) {
    const parentCond = getFieldCondition(targetField)
    if (parentCond && !evaluateCondition(parentCond, values, allFields, visited)) {
      return false
    }
  }

  const rawVal = values[targetName]
  const op = condition.operator || 'equals'
  const expected = String(condition.value ?? '').trim().toLowerCase()

  if (op === 'filled') {
    if (rawVal === undefined || rawVal === null || rawVal === '') return false
    if (Array.isArray(rawVal)) return rawVal.length > 0
    if (typeof rawVal === 'object') return Boolean(rawVal.path || rawVal.name)
    return String(rawVal).trim() !== ''
  }

  if (op === 'empty') {
    if (rawVal === undefined || rawVal === null || rawVal === '') return true
    if (Array.isArray(rawVal)) return rawVal.length === 0
    if (typeof rawVal === 'object') return !rawVal.path && !rawVal.name
    return String(rawVal).trim() === ''
  }

  if (op === 'equals') {
    if (Array.isArray(rawVal)) {
      return rawVal.map(v => String(v).toLowerCase()).includes(expected)
    }
    return String(rawVal ?? '').toLowerCase() === expected
  }

  if (op === 'not_equals') {
    if (Array.isArray(rawVal)) {
      return !rawVal.map(v => String(v).toLowerCase()).includes(expected)
    }
    return String(rawVal ?? '').toLowerCase() !== expected
  }

  if (op === 'in') {
    const allowed = expected.split(',').map(s => s.trim().toLowerCase()).filter(Boolean)
    if (Array.isArray(rawVal)) {
      return rawVal.some(v => allowed.includes(String(v).toLowerCase()))
    }
    return allowed.includes(String(rawVal ?? '').toLowerCase())
  }

  return true
}

export function isFieldVisible(field, values = {}, allFields = []) {
  const condition = getFieldCondition(field)
  if (!condition) return true
  return evaluateCondition(condition, values, allFields)
}

export function filterVisibleFields(fields = [], values = {}) {
  return fields.filter(f => isFieldVisible(f, values, fields))
}

export function pruneHiddenValues(fields = [], values = {}) {
  const visibleNames = new Set(filterVisibleFields(fields, values).map(f => f.name))
  const pruned = Object.create(null)
  for (const [key, val] of Object.entries(values)) {
    if (visibleNames.has(key)) {
      pruned[key] = val
    }
  }
  return pruned
}

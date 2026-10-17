export function sortedFields(fields) {
  return [...fields].sort((a, b) => a.order - b.order || a.id - b.id)
}

export function initialValues(fields, saved = []) {
  const stored = new Map(saved.map(item => [item.name, item.value]))
  return Object.fromEntries(fields.map(field => {
    const value = stored.get(field.name)
    return [field.name, field.type === 'checkbox' ? (Array.isArray(value) ? [...value] : []) : value ?? '']
  }))
}

export function submissionValues(fields, values) {
  return Object.fromEntries(fields.filter(field => {
    const value = values[field.name]
    return value !== undefined && value !== null && value !== '' && (!Array.isArray(value) || value.length > 0)
  }).map(field => [field.name, Array.isArray(values[field.name]) ? [...values[field.name]] : values[field.name]]))
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

export function fieldPayload(field) {
  const payload = { label: field.label.trim(), name: field.name.trim(), type: field.type, is_required: !!field.is_required, order: Number(field.order) }
  if (['select', 'radio', 'checkbox'].includes(field.type)) {
    const options = field.optionsText.split('\n').map(line => line.trim()).filter(Boolean).map(line => {
      const separator = line.indexOf('|')
      const value = (separator < 0 ? line : line.slice(0, separator)).trim()
      const label = (separator < 0 ? line : line.slice(separator + 1)).trim()
      return { value, label: label || value }
    })
    if (!options.length || options.some(option => !option.value)) throw new Error('Informe pelo menos uma opção com valor e rótulo.')
    if (new Set(options.map(option => option.value)).size !== options.length) throw new Error('Cada opção deve ter um valor diferente.')
    payload.options = options
  }
  return payload
}

/**
 * Operações puras sobre a coleção local de campos do construtor.
 * Permitem atualizar a tela imediatamente com a resposta da API (ou de forma
 * otimista), sem recarregar a página inteira a cada alteração.
 */
import { sortedFields } from './formValues.js'

/** Extrai o campo devolvido por store/update (`data` é o campo; aceita `data.field`). */
export function fieldFromResponse(response) {
  const data = response?.data
  if (data && typeof data === 'object') {
    if (data.id !== undefined && data.id !== null) return data
    if (data.field && data.field.id !== undefined && data.field.id !== null) return data.field
  }
  return null
}

/** Insere ou substitui um campo (por id) e devolve uma nova lista ordenada. */
export function upsertField(fields, field) {
  if (!field || field.id === undefined || field.id === null) return sortedFields(fields)
  const exists = fields.some(item => item.id === field.id)
  const next = exists
    ? fields.map(item => (item.id === field.id ? field : item))
    : [...fields, field]
  return sortedFields(next)
}

/** Remove um campo por id e devolve uma nova lista ordenada. */
export function removeFieldById(fields, id) {
  return sortedFields(fields.filter(item => item.id !== id))
}

/** Aplica novas ordens (`{ [id]: order }`) sem mutar a lista original. */
export function applyFieldOrders(fields, orders) {
  return sortedFields(fields.map(item => (
    Object.prototype.hasOwnProperty.call(orders, item.id)
      ? { ...item, order: orders[item.id] }
      : item
  )))
}

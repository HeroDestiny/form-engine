import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  applyFieldOrders,
  fieldFromResponse,
  removeFieldById,
  upsertField,
} from '../src/utils/fieldCollection.js'

const here = path.dirname(fileURLToPath(import.meta.url))
const builderSource = fs.readFileSync(path.join(here, '..', 'src', 'views', 'FormFieldsView.vue'), 'utf8')

const base = [
  { id: 1, name: 'a', label: 'A', type: 'text', order: 1 },
  { id: 2, name: 'b', label: 'B', type: 'text', order: 2 },
  { id: 3, name: 'c', label: 'C', type: 'text', order: 3 },
]

test('fieldFromResponse extrai o campo de store/update (data ou data.field)', () => {
  assert.deepEqual(fieldFromResponse({ data: { id: 9, name: 'x' } }), { id: 9, name: 'x' })
  assert.deepEqual(fieldFromResponse({ data: { field: { id: 8, name: 'y' } } }), { id: 8, name: 'y' })
  assert.equal(fieldFromResponse(null), null)
  assert.equal(fieldFromResponse({ data: null }), null)
  assert.equal(fieldFromResponse({ data: { message: 'sem id' } }), null)
})

test('upsertField insere, substitui por id e mantém a ordenação sem mutar a lista', () => {
  const snapshot = JSON.stringify(base)
  const inserted = upsertField(base, { id: 4, name: 'd', order: 2 })
  assert.deepEqual(inserted.map(f => f.id), [1, 2, 4, 3])
  const replaced = upsertField(base, { id: 2, name: 'b', label: 'B2', type: 'text', order: 2 })
  assert.equal(replaced.length, 3)
  assert.equal(replaced.find(f => f.id === 2).label, 'B2')
  assert.equal(JSON.stringify(base), snapshot)
  assert.equal(upsertField(base, {}).length, 3)
})

test('removeFieldById remove apenas o campo informado', () => {
  const next = removeFieldById(base, 2)
  assert.deepEqual(next.map(f => f.id), [1, 3])
  assert.equal(base.length, 3)
})

test('applyFieldOrders reordena de forma otimista e imutável', () => {
  const next = applyFieldOrders(base, { 1: 2, 2: 1 })
  assert.deepEqual(next.map(f => f.id), [2, 1, 3])
  assert.equal(base[0].order, 1)
})

test('o construtor não recarrega a página nem bloqueia o editor a cada alteração', () => {
  // Recarga completa (com esqueleto) só na primeira carga e em "Tentar novamente".
  const loadDataCalls = builderSource.match(/await loadData\(\)/g) ?? []
  assert.equal(loadDataCalls.length, 1, 'loadData() deve ser chamado somente no onMounted')
  assert.match(builderSource, /onMounted\(async \(\) => \{\s*await loadData\(\)/)

  // O formulário do editor não é desabilitado enquanto salva.
  assert.doesNotMatch(builderSource, /<fieldset[^>]*:disabled="state\.saving"/)

  // Ações por campo usam bloqueio individual, não global.
  assert.match(builderSource, /busyIds\.has\(field\.id\)/)

  // Remoção funciona: o handler existe e atualiza a lista localmente.
  assert.match(builderSource, /async function confirmDeleteField\(\)/)
  assert.match(builderSource, /removeFieldById\(state\.fields, field\.id\)/)

  // Indicador de salvamento acessível e discreto.
  assert.match(builderSource, /class="builder-sync-status"[^>]*role="status"[^>]*aria-live="polite"/)
})

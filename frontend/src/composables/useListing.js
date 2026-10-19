import { getCurrentScope, onScopeDispose, reactive, ref } from 'vue'

export function errorMessage(error) {
  return Object.values(error?.body?.errors || {})[0]?.[0]
    || error?.body?.message || 'Não foi possível carregar os dados. Verifique sua conexão e tente novamente.'
}

export function validatePeriod(filters) {
  return filters.date_from && filters.date_to && filters.date_to < filters.date_from
    ? 'A data final deve ser maior ou igual à data inicial.' : ''
}

export function useListing(fetcher, collection, defaults = {}, { paginated = true, validate = () => '' } = {}) {
  const filters = reactive({ ...defaults })
  const applied = ref({ ...defaults })
  const page = ref(1)
  const perPage = ref(20)
  const state = reactive({ items: [], pagination: null, total: 0, loading: false, error: '' })
  let controller
  let request = 0

  async function load() {
    controller?.abort()
    controller = new AbortController()
    const current = ++request
    state.loading = true
    state.error = ''
    try {
      const params = { ...applied.value, ...(paginated ? { page: page.value, per_page: perPage.value } : {}) }
      const response = await fetcher(params, { signal: controller.signal })
      if (request !== current) return
      const data = response?.data ?? {}
      if (paginated && data.pagination && page.value > Math.max(1, data.pagination.last_page)) {
        page.value = Math.max(1, data.pagination.last_page)
        return await load()
      }
      state.items = data[collection] ?? []
      state.pagination = data.pagination ?? null
      state.total = data.pagination?.total ?? data.total ?? state.items.length
    } catch (error) {
      if (request === current) {
        state.items = []
        state.pagination = null
        state.total = 0
        state.error = errorMessage(error)
      }
    } finally {
      if (request === current) state.loading = false
    }
  }

  function apply() {
    const next = Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]))
    const validation = validate(next)
    if (validation) {
      state.error = validation
      return
    }
    Object.assign(filters, next)
    applied.value = next
    page.value = 1
    return load()
  }

  function reset() {
    Object.assign(filters, defaults)
    return apply()
  }

  function setPage(value) {
    if (state.loading || value === page.value || value < 1 || value > (state.pagination?.last_page ?? 1)) return
    page.value = value
    return load()
  }

  function setPerPage(value) {
    perPage.value = Number(value)
    page.value = 1
    return load()
  }

  if (getCurrentScope()) onScopeDispose(() => { request++; controller?.abort() })
  return { state, filters, applied, page, perPage, load, apply, reset, setPage, setPerPage }
}

import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useListing } from './useListing.js'
import { decodeListQuery, encodeListQuery } from '../utils/listQuery.js'

// The route is the committed query. Watching route.query keeps the component instance
// alive across pagination and filter changes while still supporting refresh and browser Back/Forward.
export function useQueryListing(fetcher, collection, defaults = {}, options = {}) {
  const route = useRoute()
  const router = useRouter()
  const paginated = options.paginated !== false
  const listing = useListing(fetcher, collection, defaults, options)

  function syncFromQuery(query, preserveFilters = false) {
    const restored = decodeListQuery(query, defaults, paginated)
    if (!preserveFilters) {
      Object.assign(listing.filters, restored.filters)
    }
    listing.applied.value = restored.filters
    listing.page.value = restored.page
    listing.perPage.value = restored.perPage
    return restored
  }

  let currentRestored = syncFromQuery(route.query)
  let suppressWatch = false

  async function load() {
    await listing.load()
    if (!listing.state.error && listing.page.value !== currentRestored.page) {
      suppressWatch = true
      try {
        await router.replace({ path: route.path, query: encodeListQuery(listing.applied.value, defaults, listing.page.value, listing.perPage.value, paginated) })
        currentRestored = decodeListQuery(route.query, defaults, paginated)
      } finally {
        suppressWatch = false
      }
    }
  }

  watch(
    () => route.fullPath,
    (nextFullPath, prevFullPath) => {
      if (suppressWatch || nextFullPath === prevFullPath) return
      if (nextFullPath.split('?')[0] !== prevFullPath?.split('?')[0]) return
      currentRestored = syncFromQuery(route.query)
      void load()
    },
  )

  async function navigate(filters, page, perPage, preserveFilters = false) {
    const next = Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]))
    const error = options.validate?.(next)
    if (error) { listing.state.error = error; return }
    const target = { path: route.path, query: encodeListQuery(next, defaults, page, perPage, paginated) }
    if (router.resolve(target).fullPath === route.fullPath) {
      if (!preserveFilters) Object.assign(listing.filters, next)
      listing.applied.value = next
      listing.page.value = page
      listing.perPage.value = perPage
      currentRestored = { filters: { ...next }, page, perPage }
      return load()
    }
    suppressWatch = true
    try {
      await router.push(target)
      currentRestored = syncFromQuery(route.query, preserveFilters)
    } finally {
      suppressWatch = false
    }
    return load()
  }

  return {
    ...listing, load,
    apply: () => navigate(listing.filters, 1, listing.perPage.value, false),
    reset: () => navigate(defaults, 1, listing.perPage.value, false),
    setPage: page => !listing.state.loading && page >= 1 && page <= (listing.state.pagination?.last_page ?? 1) ? navigate(listing.applied.value, page, listing.perPage.value, true) : undefined,
    setPerPage: size => navigate(listing.applied.value, 1, Number(size), true),
  }
}

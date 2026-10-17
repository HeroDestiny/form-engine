import { useRoute, useRouter } from 'vue-router'
import { useListing } from './useListing.js'
import { decodeListQuery, encodeListQuery } from '../utils/listQuery.js'

// The route is the committed query. RootApp remounts pages when fullPath changes,
// so refresh, browser Back and explicit detail returns all restore the same list.
export function useQueryListing(fetcher, collection, defaults = {}, options = {}) {
  const route = useRoute()
  const router = useRouter()
  const paginated = options.paginated !== false
  const listing = useListing(fetcher, collection, defaults, options)
  const restored = decodeListQuery(route.query, defaults, paginated)
  Object.assign(listing.filters, restored.filters)
  listing.applied.value = restored.filters
  listing.page.value = restored.page
  listing.perPage.value = restored.perPage

  async function navigate(filters, page, perPage) {
    const next = Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]))
    const error = options.validate?.(next)
    if (error) { listing.state.error = error; return }
    const target = { path: route.path, query: encodeListQuery(next, defaults, page, perPage, paginated) }
    if (router.resolve(target).fullPath === route.fullPath) {
      Object.assign(listing.filters, next)
      listing.applied.value = next
      listing.page.value = page
      listing.perPage.value = perPage
      return listing.load()
    }
    await router.push(target)
  }

  async function load() {
    await listing.load()
    if (!listing.state.error && listing.page.value !== restored.page) {
      await router.replace({ path: route.path, query: encodeListQuery(listing.applied.value, defaults, listing.page.value, listing.perPage.value, paginated) })
    }
  }

  return {
    ...listing, load,
    apply: () => navigate(listing.filters, 1, listing.perPage.value),
    reset: () => navigate(defaults, 1, listing.perPage.value),
    setPage: page => !listing.state.loading && page >= 1 && page <= (listing.state.pagination?.last_page ?? 1) ? navigate(listing.applied.value, page, listing.perPage.value) : undefined,
    setPerPage: size => navigate(listing.applied.value, 1, Number(size)),
  }
}

import type { MaybeRefOrGetter } from 'vue'
import type { Resource, Shop } from '~/types'

/** Public shop data shared (same key) between the storefront layout and its pages. */
export function useStorefrontShop(slug: MaybeRefOrGetter<string>) {
  const api = useApi()

  return useAsyncData(
    () => `storefront-${toValue(slug)}`,
    () => api<Resource<Shop>>(`/storefront/${toValue(slug)}`).then(response => response.data),
  )
}

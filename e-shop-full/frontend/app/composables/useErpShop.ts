import type { Resource, Shop } from '~/types'

/**
 * Loads the shop of an ERP page and exposes its installed applications.
 * The `module` flag tells whether the page's own application is installed.
 */
export function useErpShop(shopId: MaybeRefOrGetter<string | number>, module?: string) {
  const api = useApi()
  const id = computed(() => String(toValue(shopId)))

  const { data: shop, status, error, refresh } = useAsyncData(
    () => `erp-shop-${id.value}`,
    () => api<Resource<Shop>>(`/shops/${id.value}`).then(response => response.data),
    { server: false },
  )

  const loading = computed(() => status.value === 'pending' || status.value === 'idle')
  const modules = computed<string[]>(() => shop.value?.modules ?? ['sales'])
  const hasModule = (key: string): boolean => modules.value.includes(key)
  const isInstalled = computed(() => !module || hasModule(module))
  const basePath = computed(() => `/dashboard/boutiques/${id.value}/erp`)
  const apiBase = computed(() => `/shops/${id.value}/erp`)

  return { shop, loading, error, refresh, modules, hasModule, isInstalled, basePath, apiBase }
}

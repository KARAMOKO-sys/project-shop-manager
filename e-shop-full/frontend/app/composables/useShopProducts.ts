import type { Paginated, Product } from '~/types'

/** All products of a shop (up to 10 pages), for line pickers in ERP forms. */
export function useShopProducts(shopId: MaybeRefOrGetter<string | number>) {
  const api = useApi()
  const products = useState<Product[] | null>(`erp-products-${toValue(shopId)}`, () => null)
  const loading = ref(false)

  async function load(force = false): Promise<Product[]> {
    if (products.value && !force) {
      return products.value
    }

    loading.value = true

    try {
      const all: Product[] = []
      let page = 1
      let last = 1

      do {
        const response = await api<Paginated<Product>>(`/shops/${toValue(shopId)}/products?page=${page}`)
        all.push(...response.data)
        last = Math.min(response.meta?.last_page ?? 1, 10)
        page++
      } while (page <= last)

      products.value = all

      return all
    }
    finally {
      loading.value = false
    }
  }

  return { products, loading, load }
}

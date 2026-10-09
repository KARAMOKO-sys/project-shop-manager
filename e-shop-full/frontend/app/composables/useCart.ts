import type { CartItem, Product } from '~/types'

/** Per-shop cart kept in shared state and persisted to localStorage (restored after hydration). */
export function useCart(shopSlug: string) {
  const storageKey = `shopai_cart_${shopSlug}`
  const items = useState<CartItem[]>(`cart-${shopSlug}`, () => [])
  const restored = useState<boolean>(`cart-restored-${shopSlug}`, () => false)

  function persist(): void {
    if (!import.meta.client) {
      return
    }

    try {
      localStorage.setItem(storageKey, JSON.stringify(items.value))
    }
    catch {
      // Storage may be unavailable (private mode): the cart still works for the session.
    }
  }

  function restore(): void {
    if (restored.value) {
      return
    }

    restored.value = true

    try {
      const parsed: unknown = JSON.parse(localStorage.getItem(storageKey) ?? '[]')

      if (Array.isArray(parsed)) {
        items.value = parsed as CartItem[]
      }
    }
    catch {
      items.value = []
    }
  }

  if (import.meta.client && getCurrentInstance()) {
    onMounted(restore)
  }

  function add(product: Product, quantity = 1): void {
    const existing = items.value.find(item => item.product_id === product.id)

    if (existing) {
      existing.quantity += quantity
    }
    else {
      items.value.push({
        product_id: product.id,
        slug: product.slug,
        name: product.name,
        price: Number(product.price),
        currency: product.currency,
        image_url: product.image_url,
        quantity,
      })
    }

    persist()
  }

  function setQuantity(productId: number, quantity: number): void {
    if (quantity <= 0) {
      remove(productId)

      return
    }

    const item = items.value.find(entry => entry.product_id === productId)

    if (item) {
      item.quantity = quantity
      persist()
    }
  }

  function remove(productId: number): void {
    items.value = items.value.filter(item => item.product_id !== productId)
    persist()
  }

  function clear(): void {
    items.value = []
    persist()
  }

  const count = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  const subtotal = computed(() => items.value.reduce((sum, item) => sum + item.price * item.quantity, 0))

  return { items, count, subtotal, add, setQuantity, remove, clear }
}

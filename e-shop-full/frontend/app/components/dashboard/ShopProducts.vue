<script setup lang="ts">
import type { Paginated, Product, Resource, Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const api = useApi()
const toast = useToast()

const page = ref(1)
const expanded = ref<number | null>(null)
const busy = ref<number | null>(null)

const { data: products, status, error, refresh } = useAsyncData(
  `shop-products-${props.shop.id}`,
  () => api<Paginated<Product>>(`/shops/${props.shop.id}/products`, { query: { page: page.value } }),
  { server: false, watch: [page] },
)

function replaceProduct(updated: Product): void {
  if (products.value) {
    products.value = { ...products.value, data: products.value.data.map(item => (item.id === updated.id ? updated : item)) }
  }
}

async function remoderate(product: Product): Promise<void> {
  busy.value = product.id

  try {
    const { data } = await api<Resource<Product>>(`/shops/${props.shop.id}/products/${product.id}/moderate`, { method: 'POST' })
    replaceProduct(data)
    toast.success(`« ${data.name} » a été revérifié par l’agent IA.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La vérification a échoué.'))
  }
  finally {
    busy.value = null
  }
}

async function destroy(product: Product): Promise<void> {
  if (!window.confirm(`Supprimer définitivement « ${product.name} » ?`)) {
    return
  }

  busy.value = product.id

  try {
    await api(`/shops/${props.shop.id}/products/${product.id}`, { method: 'DELETE' })
    toast.success('Produit supprimé.')
    await refresh()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La suppression a échoué.'))
  }
  finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="card">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h2 class="h5 fw-bold mb-0">Produits <span v-if="products?.meta" class="text-muted fw-normal">({{ products.meta.total }})</span></h2>
      <NuxtLink :to="`/dashboard/boutiques/${shop.id}/produits/nouveau`" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" />Ajouter un produit
      </NuxtLink>
    </div>
    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des produits…" />
    <AppState v-else-if="error" variant="error" title="Impossible de charger les produits" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <AppState v-else-if="!products?.data.length" icon="bi-box-seam" title="Aucun produit pour l’instant" text="Ajoutez votre premier produit : l’IA peut même rédiger la description pour vous.">
      <NuxtLink :to="`/dashboard/boutiques/${shop.id}/produits/nouveau`" class="btn btn-primary">
        <i class="bi bi-stars me-1" aria-hidden="true" />Ajouter mon premier produit
      </NuxtLink>
    </AppState>
    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <caption class="visually-hidden">Liste des produits de la boutique</caption>
        <thead class="table-light">
          <tr>
            <th scope="col">Produit</th>
            <th scope="col">Prix</th>
            <th scope="col">Stock</th>
            <th scope="col">Statut</th>
            <th scope="col">Vérification IA</th>
            <th scope="col" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="product in products.data" :key="product.id">
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="table-thumb">
                    <ProductImage :src="product.image_url" :alt="product.name" :color="shop.theme?.primary" ratio="1 / 1" />
                  </div>
                  <div class="min-w-0">
                    <p class="fw-semibold mb-0">{{ product.name }}</p>
                    <p class="small text-muted mb-0">{{ product.category?.name ?? 'Sans catégorie' }}</p>
                  </div>
                </div>
              </td>
              <td class="text-nowrap"><PriceTag :amount="product.price" :currency="product.currency" /></td>
              <td>
                <span :class="{ 'text-danger fw-semibold': product.stock_quantity <= 0 }">{{ product.stock_quantity }}</span>
              </td>
              <td>
                <span class="badge rounded-pill" :class="product.status === 'published' ? 'text-bg-primary' : 'text-bg-light'">
                  {{ product.status === 'published' ? 'Publié' : 'Brouillon' }}
                </span>
              </td>
              <td>
                <button
                  type="button"
                  class="btn btn-link p-0 text-decoration-none"
                  :aria-expanded="expanded === product.id"
                  :aria-controls="`moderation-${product.id}`"
                  :title="product.moderation?.reasons?.join(' · ') || 'Voir le détail'"
                  @click="expanded = expanded === product.id ? null : product.id"
                >
                  <ModerationBadge :moderation="product.moderation" />
                  <i class="bi bi-chevron-down small ms-1 text-muted" aria-hidden="true" />
                  <span class="visually-hidden">Afficher le détail de la vérification</span>
                </button>
              </td>
              <td class="text-end text-nowrap">
                <NuxtLink :to="`/dashboard/boutiques/${shop.id}/produits/${product.id}`" class="btn btn-sm btn-light" :aria-label="`Modifier ${product.name}`">
                  <i class="bi bi-pencil" aria-hidden="true" />
                </NuxtLink>
                <button type="button" class="btn btn-sm btn-light ms-1" :disabled="busy === product.id" :aria-label="`Revérifier ${product.name}`" title="Re-vérifier avec l’IA" @click="remoderate(product)">
                  <span v-if="busy === product.id" class="spinner-border spinner-border-sm" aria-hidden="true" />
                  <i v-else class="bi bi-arrow-repeat" aria-hidden="true" />
                </button>
                <button type="button" class="btn btn-sm btn-light text-danger ms-1" :disabled="busy === product.id" :aria-label="`Supprimer ${product.name}`" @click="destroy(product)">
                  <i class="bi bi-trash" aria-hidden="true" />
                </button>
              </td>
            </tr>
            <tr v-if="expanded === product.id" :id="`moderation-${product.id}`">
              <td colspan="6" class="bg-light">
                <DashboardModerationDetails :moderation="product.moderation" />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div class="px-3 pb-3">
      <AppPagination v-model:page="page" :meta="products?.meta" />
    </div>
  </div>
</template>

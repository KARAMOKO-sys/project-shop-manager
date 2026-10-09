<script setup lang="ts">
import type { Product, Resource, Shop } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = String(route.params.id)
const productId = String(route.params.productId)
const justSaved = ref(route.query.cree === '1')
const remoderating = ref(false)

const { data: shop } = useAsyncData(
  `shop-${shopId}`,
  () => api<Resource<Shop>>(`/shops/${shopId}`).then(response => response.data),
  { server: false },
)

const { data: product, status, error, refresh } = useAsyncData(
  `product-${shopId}-${productId}`,
  () => api<Resource<Product>>(`/shops/${shopId}/products/${productId}`).then(response => response.data),
  { server: false },
)

useSeoMeta({ title: () => product.value?.name ?? 'Modifier le produit' })

const moderationAlert = computed(() => {
  switch (product.value?.moderation?.status) {
    case 'approved':
      return { variant: 'success', icon: 'bi-shield-check', text: 'Produit vérifié : il est visible par vos clients (s’il est publié).' }
    case 'rejected':
      return { variant: 'danger', icon: 'bi-shield-x', text: 'Produit refusé : corrigez les points ci-dessous puis relancez la vérification.' }
    default:
      return { variant: 'warning', icon: 'bi-hourglass-split', text: 'Vérification en cours : un modérateur examine ce produit. Il sera visible dès validation.' }
  }
})

function onSaved(updated: Product): void {
  product.value = updated
  justSaved.value = true
  toast.success('Modifications enregistrées et revérifiées par l’agent IA.')
}

async function remoderate(): Promise<void> {
  remoderating.value = true

  try {
    const { data } = await api<Resource<Product>>(`/shops/${shopId}/products/${productId}/moderate`, { method: 'POST' })
    product.value = data
    toast.success('Vérification relancée.')
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La vérification a échoué.'))
  }
  finally {
    remoderating.value = false
  }
}
</script>

<template>
  <div>
    <nav aria-label="Fil d’Ariane">
      <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><NuxtLink to="/dashboard">Mes boutiques</NuxtLink></li>
        <li class="breadcrumb-item"><NuxtLink :to="`/dashboard/boutiques/${shopId}?onglet=produits`">{{ shop?.name ?? 'Boutique' }}</NuxtLink></li>
        <li class="breadcrumb-item active" aria-current="page">{{ product?.name ?? 'Produit' }}</li>
      </ol>
    </nav>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement du produit…" />
    <AppState v-else-if="error || !product" variant="error" title="Produit introuvable" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <template v-else>
      <h1 class="h3 fw-bold mb-4">{{ product.name }}</h1>
      <div class="row g-4">
        <div class="col-xl-8 order-2 order-xl-1">
          <DashboardProductForm
            :key="product.id"
            :shop-id="shopId"
            :product="product"
            :currency="shop?.currency ?? product.currency"
            :color="shop?.theme?.primary"
            @saved="onSaved"
          />
        </div>
        <div class="col-xl-4 order-1 order-xl-2">
          <aside class="card p-4 position-sticky" style="top: 1rem;" aria-labelledby="moderation-title" aria-live="polite">
            <h2 id="moderation-title" class="h5 fw-bold mb-3"><i class="bi bi-robot me-1" aria-hidden="true" />Vérification IA</h2>
            <div class="alert d-flex gap-2" :class="`alert-${moderationAlert.variant}`" role="status">
              <i class="bi" :class="moderationAlert.icon" aria-hidden="true" />
              <span>
                <strong v-if="justSaved" class="d-block">Résultat de l’analyse</strong>
                {{ moderationAlert.text }}
              </span>
            </div>
            <p class="small text-muted">Notre agent IA vérifie chaque produit avant qu’il soit visible du public : contrefaçons, produits interdits, prix suspects…</p>
            <DashboardModerationDetails :moderation="product.moderation" />
            <button type="button" class="btn btn-outline-primary w-100 mt-3" :disabled="remoderating" @click="remoderate">
              <span v-if="remoderating" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
              <i v-else class="bi bi-arrow-repeat me-1" aria-hidden="true" />
              Relancer la vérification
            </button>
            <NuxtLink v-if="product.is_public && shop" :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="btn btn-link w-100 mt-2" target="_blank">
              Voir dans la boutique <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true" />
            </NuxtLink>
          </aside>
        </div>
      </div>
    </template>
  </div>
</template>

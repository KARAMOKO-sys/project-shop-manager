<script setup lang="ts">
import type { Product, Resource } from '~/types'

definePageMeta({ layout: 'storefront' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const slug = String(route.params.slug)
const productSlug = String(route.params.productSlug)

const { data: shop } = await useStorefrontShop(slug)
const { add } = useCart(slug)

const { data: product, status, error } = await useAsyncData(
  `storefront-product-${slug}-${productSlug}`,
  () => api<Resource<Product>>(`/storefront/${slug}/products/${productSlug}`).then(response => response.data),
)

/** Recommendations are optional: any failure simply hides the section. */
const { data: recommendations } = useAsyncData(
  `storefront-recommendations-${slug}-${productSlug}`,
  () => api<Resource<Product[]>>(`/storefront/${slug}/products/${productSlug}/recommendations`)
    .then(response => response.data ?? [])
    .catch(() => [] as Product[]),
  { server: false, default: () => [] as Product[] },
)

const quantity = ref(1)
const primary = computed(() => resolveTheme(shop.value?.theme).primary)
const maxQuantity = computed(() => Math.max(1, product.value?.stock_quantity ?? 1))

useSeoMeta({
  title: () => (product.value ? `${product.value.name} — ${shop.value?.name ?? ''}` : 'Produit'),
  description: () => product.value?.description?.slice(0, 160) ?? '',
  ogTitle: () => product.value?.name ?? 'Produit',
  ogImage: () => product.value?.image_url ?? undefined,
})

function changeQuantity(delta: number): void {
  quantity.value = Math.min(maxQuantity.value, Math.max(1, quantity.value + delta))
}

function addRecommendation(recommended: Product): void {
  add(recommended, 1)
  toast.success(`« ${recommended.name} » ajouté au panier.`)
}

function addToCart(): void {
  if (!product.value) {
    return
  }

  add(product.value, quantity.value)
  toast.success(`${quantity.value} × « ${product.value.name} » ajouté au panier.`)
}
</script>

<template>
  <div class="container py-4">
    <AppState v-if="status === 'pending'" loading text="Chargement du produit…" />
    <AppState v-else-if="error || !product" variant="error" title="Produit introuvable" text="Ce produit n’existe plus ou n’est pas encore disponible.">
      <NuxtLink :to="`/boutique/${slug}`" class="btn btn-primary">Retour à la boutique</NuxtLink>
    </AppState>
    <template v-else>
      <nav aria-label="Fil d’Ariane">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><NuxtLink :to="`/boutique/${slug}`">{{ shop?.name ?? 'Boutique' }}</NuxtLink></li>
          <li v-if="product.category" class="breadcrumb-item">{{ product.category.name }}</li>
          <li class="breadcrumb-item active" aria-current="page">{{ product.name }}</li>
        </ol>
      </nav>
      <div class="row g-4 g-lg-5">
        <div class="col-md-6">
          <div class="card overflow-hidden">
            <ProductImage :src="product.image_url" :alt="product.name" :color="primary" ratio="1 / 1" />
          </div>
        </div>
        <div class="col-md-6">
          <h1 class="h2 fw-bold">{{ product.name }}</h1>
          <p class="fs-3 my-3"><PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" /></p>
          <p class="mb-3">
            <span v-if="product.stock_quantity > 0" class="badge rounded-pill text-bg-success"><i class="bi bi-check2 me-1" aria-hidden="true" />En stock</span>
            <span v-else class="badge rounded-pill text-bg-secondary">Épuisé</span>
            <span v-if="product.moderation?.status === 'approved'" class="badge rounded-pill text-bg-light ms-1">
              <i class="bi bi-shield-check text-success me-1" aria-hidden="true" />Produit vérifié
            </span>
          </p>
          <p v-if="product.description" class="text-muted" style="white-space: pre-line;">{{ product.description }}</p>
          <div v-if="product.stock_quantity > 0" class="d-flex gap-3 align-items-end flex-wrap my-4">
            <div>
              <label for="product-quantity" class="form-label small">Quantité</label>
              <div class="input-group" style="width: 9rem;">
                <button type="button" class="btn btn-outline-secondary" aria-label="Diminuer la quantité" @click="changeQuantity(-1)">−</button>
                <input id="product-quantity" v-model.number="quantity" type="number" class="form-control text-center" min="1" :max="maxQuantity" inputmode="numeric">
                <button type="button" class="btn btn-outline-secondary" aria-label="Augmenter la quantité" @click="changeQuantity(1)">+</button>
              </div>
            </div>
            <button type="button" class="btn btn-primary btn-lg flex-grow-1" @click="addToCart">
              <i class="bi bi-bag-plus me-1" aria-hidden="true" />Ajouter au panier
            </button>
          </div>
          <div v-if="product.tags?.length" class="d-flex flex-wrap gap-2 mb-4">
            <span v-for="tag in product.tags" :key="tag" class="badge rounded-pill text-bg-light">#{{ tag }}</span>
          </div>
          <ul class="list-unstyled small text-muted d-grid gap-2 border-top pt-3">
            <li><i class="bi bi-truck me-2" aria-hidden="true" />Livraison rapide dans votre ville</li>
            <li><i class="bi bi-phone me-2" aria-hidden="true" />Paiement Mobile Money ou à la livraison</li>
            <li><i class="bi bi-chat-dots me-2" aria-hidden="true" />Une question ? Notre assistant répond 24h/24</li>
          </ul>
        </div>
      </div>
      <section v-if="recommendations?.length" class="mt-5" aria-labelledby="recommendations-title">
        <h2 id="recommendations-title" class="h4 fw-bold mb-3">Les clients ont aussi acheté</h2>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
          <div v-for="item in recommendations" :key="item.id" class="col">
            <ProductCard :product="item" :shop-slug="slug" :color="primary" addable @add="addRecommendation" />
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

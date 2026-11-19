<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Single-product storefront: a sales page for the flagship product (the first, featured one), then the others. */
const props = defineProps<{
  shop: Shop
  theme: Theme
  products: Product[]
  meta?: PaginationMeta | null
  loading: boolean
  failed: boolean
  category: string
  search: string
}>()
const page = defineModel<number>('page', { required: true })
const emit = defineEmits<{
  'select-category': [slug: string]
  'search': [term: string]
  'add': [product: Product, quantity?: number]
  'retry': []
}>()

const quantity = ref(1)

const hero = computed(() => props.products[0] ?? null)
const others = computed(() => props.products.slice(1))
const inStock = computed(() => (hero.value?.stock_quantity ?? 0) > 0)

/** Only promises the shop can keep: they follow its payment settings. */
const reassurances = computed(() => [
  { icon: 'bi-shield-check', label: 'Produit vérifié par notre IA' },
  ...(props.shop.payment_methods?.includes('cash_on_delivery') ? [{ icon: 'bi-cash-coin', label: 'Paiement à la livraison' }] : []),
  ...(props.shop.payment_methods?.includes('flutterwave') ? [{ icon: 'bi-phone', label: 'Mobile Money et carte' }] : []),
  { icon: 'bi-truck', label: 'Suivi de commande en ligne' },
])

function addHero(): void {
  if (hero.value) {
    emit('add', hero.value, quantity.value)
  }
}
</script>

<template>
  <div>
    <div id="catalogue" class="container py-4 py-lg-5">
      <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!hero" :filtered="Boolean(search || category)" @retry="emit('retry')">
        <section v-if="hero" class="row g-4 g-lg-5 align-items-center mb-5" aria-labelledby="shop-title">
          <div class="col-lg-6">
            <div class="sf-zoom shadow-sm">
              <ProductImage :src="hero.image_url" :alt="hero.name" :color="theme.primary" ratio="1 / 1" />
            </div>
          </div>
          <div class="col-lg-6">
            <p class="sf-label text-muted mb-2">{{ shop.name }}</p>
            <h1 id="shop-title" class="sf-single-title mb-3">{{ hero.name }}</h1>
            <p class="fs-3 mb-3"><PriceTag :amount="hero.price" :currency="hero.currency" :compare-at="hero.compare_at_price" /></p>
            <p v-if="hero.description" class="text-muted mb-4" style="white-space: pre-line;">{{ hero.description }}</p>

            <div v-if="inStock" class="d-flex flex-wrap gap-2 align-items-center mb-4">
              <label for="single-quantity" class="visually-hidden">Quantité</label>
              <input id="single-quantity" v-model.number="quantity" type="number" min="1" :max="hero.stock_quantity" class="form-control form-control-lg" style="width: 6rem;">
              <button type="button" class="btn btn-primary btn-lg flex-grow-1" @click="addHero">
                <i class="bi bi-bag-plus me-1" aria-hidden="true" />Commander maintenant
              </button>
            </div>
            <p v-else class="alert alert-secondary">Ce produit est momentanément épuisé.</p>

            <ul class="row row-cols-2 g-2 list-unstyled mb-0">
              <li v-for="item in reassurances" :key="item.label" class="col">
                <span class="sf-reassurance"><i class="bi" :class="item.icon" aria-hidden="true" />{{ item.label }}</span>
              </li>
            </ul>
          </div>
        </section>

        <section v-if="others.length" aria-labelledby="others-title">
          <h2 id="others-title" class="h4 mb-3">À associer avec</h2>
          <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
            <div v-for="product in others" :key="product.id" class="col">
              <ProductCard class="sf-mini-card" :product="product" :shop-slug="shop.slug" :color="theme.primary" addable @add="emit('add', $event)" />
            </div>
          </div>
          <AppPagination v-model:page="page" :meta="meta" />
        </section>
      </StorefrontCatalogState>
    </div>

    <template v-if="hero && inStock">
      <div class="sf-buy-bar-spacer d-lg-none" aria-hidden="true" />
      <div class="sf-buy-bar d-lg-none d-flex align-items-center justify-content-between gap-2">
        <span class="fw-bold"><PriceTag :amount="hero.price" :currency="hero.currency" /></span>
        <button type="button" class="btn btn-primary" @click="addHero">
          <i class="bi bi-bag-plus me-1" aria-hidden="true" />Commander
        </button>
      </div>
    </template>
  </div>
</template>

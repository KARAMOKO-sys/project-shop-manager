<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Editorial (magazine) storefront: full-width hero, alternating "story" rows, then a 2-column grid. */
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

const STORY_COUNT = 4

const stories = computed(() => props.products.slice(0, STORY_COUNT))
const others = computed(() => props.products.slice(STORY_COUNT))
const heroImage = computed(() => props.products.find(product => product.image_url)?.image_url ?? (props.theme.hero_style === 'image' ? props.shop.logo_url : null))
const heroStyle = computed(() => (heroImage.value ? { '--hero-image': `url("${heroImage.value}")` } : {}))

function excerpt(text: string | null, length = 260): string {
  if (!text) {
    return ''
  }

  return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text
}
</script>

<template>
  <div>
    <section class="sf-editorial-hero" :style="heroStyle" aria-labelledby="shop-title">
      <div class="container py-5">
        <p class="sf-label mb-3 opacity-75">{{ shop.name }}</p>
        <h1 id="shop-title" class="sf-editorial-title mb-3" style="max-width: 48rem;">{{ shop.tagline || shop.name }}</h1>
        <p v-if="shop.description" class="lead mb-4 opacity-75" style="max-width: 38rem;">{{ excerpt(shop.description, 220) }}</p>
        <a href="#catalogue" class="btn btn-light btn-lg">Lire la collection <i class="bi bi-arrow-down ms-1" aria-hidden="true" /></a>
      </div>
    </section>

    <div id="catalogue" class="container py-4">
      <h2 class="visually-hidden">Catalogue</h2>
      <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-end mb-5">
        <ul v-if="shop.categories?.length" class="sf-underline-menu flex-grow-1" aria-label="Filtrer par catégorie">
          <li><button type="button" :aria-pressed="!category" @click="emit('select-category', '')">Tout</button></li>
          <li v-for="item in shop.categories" :key="item.id">
            <button type="button" :aria-pressed="category === item.slug" @click="emit('select-category', item.slug)">{{ item.name }}</button>
          </li>
        </ul>
        <StorefrontSearchForm class="ms-lg-auto" style="min-width: min(100%, 300px);" :search="search" @search="emit('search', $event)" />
      </div>

      <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!products.length" :filtered="Boolean(search || category)" @retry="emit('retry')">
        <section aria-label="À la une" class="d-grid gap-5 mb-5">
          <article v-for="(product, index) in stories" :key="product.id" class="row g-4 g-lg-5 align-items-center">
            <div class="col-md-6 col-lg-7" :class="{ 'order-md-2': index % 2 === 1 }">
              <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="d-block sf-story-media" tabindex="-1" aria-hidden="true">
                <ProductImage :src="product.image_url" :alt="product.name" :color="theme.primary" ratio="4 / 3" />
              </NuxtLink>
            </div>
            <div class="col-md-6 col-lg-5">
              <p class="sf-story-number mb-2">N° {{ String(index + 1).padStart(2, '0') }}<template v-if="product.category"> · {{ product.category.name }}</template></p>
              <h3 class="display-6 mb-3">
                <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="text-reset text-decoration-none">{{ product.name }}</NuxtLink>
              </h3>
              <p v-if="product.description" class="text-muted mb-4" style="white-space: pre-line;">{{ excerpt(product.description) }}</p>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="fs-5"><PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" /></span>
                <button
                  v-if="product.stock_quantity > 0"
                  type="button"
                  class="btn btn-primary"
                  :aria-label="`Ajouter ${product.name} au panier`"
                  @click="emit('add', product)"
                >
                  <i class="bi bi-bag-plus me-1" aria-hidden="true" />Ajouter
                </button>
                <span v-else class="badge text-bg-secondary">Épuisé</span>
                <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="btn btn-link px-0">Découvrir</NuxtLink>
              </div>
            </div>
          </article>
        </section>

        <section v-if="others.length" aria-labelledby="more-title">
          <h2 id="more-title" class="h3 mb-4">Toute la collection</h2>
          <div class="row row-cols-1 row-cols-md-2 g-4">
            <div v-for="product in others" :key="product.id" class="col">
              <ProductCard :product="product" :shop-slug="shop.slug" :color="theme.primary" addable @add="emit('add', $event)" />
            </div>
          </div>
        </section>
        <AppPagination v-model:page="page" :meta="meta" />
      </StorefrontCatalogState>
    </div>
  </div>
</template>

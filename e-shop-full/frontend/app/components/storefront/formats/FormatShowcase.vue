<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Showcase storefront (luxury, craft): airy hero, discreet category menu and large visuals. */
defineProps<{
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
</script>

<template>
  <div>
    <section class="sf-showcase-hero container" aria-labelledby="shop-title">
      <p class="sf-label text-muted mb-3">Collection</p>
      <h1 id="shop-title" class="sf-showcase-title mb-0">{{ shop.name }}</h1>
      <div class="sf-showcase-rule" aria-hidden="true" />
      <p v-if="shop.tagline" class="lead text-muted mx-auto mb-0" style="max-width: 34rem;">{{ shop.tagline }}</p>
    </section>

    <div id="catalogue" class="container pb-5">
      <h2 class="visually-hidden">Catalogue</h2>
      <ul v-if="shop.categories?.length" class="sf-showcase-nav mb-5" aria-label="Filtrer par catégorie">
        <li><button type="button" :aria-pressed="!category" @click="emit('select-category', '')">Tout voir</button></li>
        <li v-for="item in shop.categories" :key="item.id">
          <button type="button" :aria-pressed="category === item.slug" @click="emit('select-category', item.slug)">{{ item.name }}</button>
        </li>
      </ul>

      <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!products.length" :filtered="Boolean(search || category)" @retry="emit('retry')">
        <div class="row row-cols-2 row-cols-lg-3 g-3 g-md-5">
          <article v-for="product in products" :key="product.id" class="col sf-showcase-item text-center">
            <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="d-block sf-zoom mb-3" tabindex="-1" aria-hidden="true">
              <ProductImage :src="product.image_url" :alt="product.name" :color="theme.primary" ratio="4 / 5" />
            </NuxtLink>
            <h3 class="sf-showcase-name mb-1">
              <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="text-reset text-decoration-none">{{ product.name }}</NuxtLink>
            </h3>
            <p class="mb-2"><PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" /></p>
            <button
              v-if="product.stock_quantity > 0"
              type="button"
              class="btn btn-outline-primary btn-sm"
              :aria-label="`Ajouter ${product.name} au panier`"
              @click="emit('add', product)"
            >
              Ajouter au panier
            </button>
            <span v-else class="small text-muted">Épuisé</span>
          </article>
        </div>
        <AppPagination v-model:page="page" :meta="meta" />
      </StorefrontCatalogState>
    </div>
  </div>
</template>

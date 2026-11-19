<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Catalog storefront: compact header, category sidebar (select on phones) and a dense product grid. */
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
  <div class="container py-4">
    <section class="shop-hero mb-4 py-4" :class="`shop-hero-${theme.hero_style}`" aria-labelledby="shop-title">
      <h1 id="shop-title" class="h2 fw-bold mb-1">{{ shop.name }}</h1>
      <p v-if="shop.tagline" class="mb-0 opacity-75">{{ shop.tagline }}</p>
    </section>

    <div id="catalogue" class="row g-4">
      <aside class="col-lg-3" aria-label="Filtres du catalogue">
        <div class="card p-3 sticky-lg-top" style="top: 5.5rem;">
          <p class="sf-filter-title">Recherche</p>
          <StorefrontSearchForm :search="search" compact class="mb-3" @search="emit('search', $event)" />

          <template v-if="shop.categories?.length">
            <label for="catalog-category" class="sf-filter-title d-lg-none">Catégorie</label>
            <select
              id="catalog-category"
              class="form-select d-lg-none"
              :value="category"
              @change="emit('select-category', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Toutes les catégories</option>
              <option v-for="item in shop.categories" :key="item.id" :value="item.slug">{{ item.name }}</option>
            </select>

            <p class="sf-filter-title d-none d-lg-block">Catégories</p>
            <ul class="sf-category-list list-unstyled d-none d-lg-grid gap-1 mb-0">
              <li><button type="button" :aria-pressed="!category" @click="emit('select-category', '')">Toutes</button></li>
              <li v-for="item in shop.categories" :key="item.id">
                <button type="button" :aria-pressed="category === item.slug" @click="emit('select-category', item.slug)">{{ item.name }}</button>
              </li>
            </ul>
          </template>
        </div>
      </aside>

      <section class="col-lg-9" aria-labelledby="catalog-title">
        <div class="d-flex justify-content-between align-items-baseline mb-3">
          <h2 id="catalog-title" class="h5 fw-bold mb-0">Catalogue</h2>
          <span v-if="meta" class="small text-muted">{{ meta.total }} produit(s)</span>
        </div>
        <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!products.length" :filtered="Boolean(search || category)" @retry="emit('retry')">
          <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2 g-md-3">
            <div v-for="product in products" :key="product.id" class="col">
              <ProductCard class="sf-mini-card" :product="product" :shop-slug="shop.slug" :color="theme.primary" addable @add="emit('add', $event)" />
            </div>
          </div>
          <AppPagination v-model:page="page" :meta="meta" />
        </StorefrontCatalogState>
      </section>
    </div>
  </div>
</template>

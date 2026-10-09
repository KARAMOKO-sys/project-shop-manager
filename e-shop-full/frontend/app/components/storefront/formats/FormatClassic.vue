<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Classic storefront: hero (per hero_style), category pills, search and a grid / list / masonry of products. */
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

const heroStyle = computed(() => (props.theme.hero_style === 'image' && props.shop.logo_url ? { '--hero-image': `url("${props.shop.logo_url}")` } : {}))
</script>

<template>
  <div class="container py-4">
    <section class="shop-hero mb-4" :class="`shop-hero-${theme.hero_style}`" :style="heroStyle" aria-labelledby="shop-title">
      <div style="max-width: 40rem;">
        <h1 id="shop-title" class="display-5 fw-bold mb-3">{{ shop.name }}</h1>
        <p v-if="shop.tagline" class="lead mb-2">{{ shop.tagline }}</p>
        <p v-if="shop.description" class="mb-4 opacity-75">{{ shop.description }}</p>
        <a href="#catalogue" class="btn btn-lg" :class="theme.hero_style === 'minimal' ? 'btn-primary' : 'btn-light'">
          Découvrir nos produits <i class="bi bi-arrow-down ms-1" aria-hidden="true" />
        </a>
      </div>
    </section>

    <section id="catalogue" aria-labelledby="catalogue-title">
      <h2 id="catalogue-title" class="visually-hidden">Catalogue</h2>
      <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center mb-4">
        <ul v-if="shop.categories?.length" class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-1" aria-label="Filtrer par catégorie">
          <li class="nav-item">
            <button type="button" class="nav-link text-nowrap" :class="{ active: !category }" :aria-pressed="!category" @click="emit('select-category', '')">Tout</button>
          </li>
          <li v-for="item in shop.categories" :key="item.id" class="nav-item">
            <button type="button" class="nav-link text-nowrap" :class="{ active: category === item.slug }" :aria-pressed="category === item.slug" @click="emit('select-category', item.slug)">
              {{ item.name }}
            </button>
          </li>
        </ul>
        <StorefrontSearchForm class="ms-lg-auto" style="min-width: min(100%, 320px);" :search="search" @search="emit('search', $event)" />
      </div>

      <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!products.length" :filtered="Boolean(search || category)" @retry="emit('retry')">
        <div v-if="theme.layout === 'masonry'" class="masonry">
          <div v-for="product in products" :key="product.id">
            <ProductCard :product="product" :shop-slug="shop.slug" :color="theme.primary" addable @add="emit('add', $event)" />
          </div>
        </div>
        <div v-else class="row g-4" :class="theme.layout === 'list' ? 'row-cols-1 row-cols-lg-2' : 'row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4'">
          <div v-for="product in products" :key="product.id" class="col">
            <ProductCard :product="product" :shop-slug="shop.slug" :layout="theme.layout" :color="theme.primary" addable @add="emit('add', $event)" />
          </div>
        </div>
        <AppPagination v-model:page="page" :meta="meta" />
      </StorefrontCatalogState>
    </section>
  </div>
</template>

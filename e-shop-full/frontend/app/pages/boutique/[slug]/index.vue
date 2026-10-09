<script setup lang="ts">
import type { Paginated, Product } from '~/types'

definePageMeta({ layout: 'storefront' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const slug = String(route.params.slug)

const { data: shop, status: shopStatus, error: shopError } = await useStorefrontShop(slug)
const { add } = useCart(slug)

const category = ref(typeof route.query.categorie === 'string' ? route.query.categorie : '')
const searchDraft = ref(typeof route.query.q === 'string' ? route.query.q : '')
const search = ref(searchDraft.value)
const page = ref(1)

const { data: products, status, error, refresh } = await useAsyncData(
  `storefront-products-${slug}`,
  () => api<Paginated<Product>>(`/storefront/${slug}/products`, {
    query: { category: category.value || undefined, search: search.value || undefined, page: page.value },
  }),
  { watch: [category, search, page] },
)

const theme = computed(() => resolveTheme(shop.value?.theme))
const heroStyle = computed(() => (theme.value.hero_style === 'image' && shop.value?.logo_url ? { '--hero-image': `url("${shop.value.logo_url}")` } : {}))

useSeoMeta({
  title: () => shop.value?.name ?? 'Boutique',
  description: () => shop.value?.tagline ?? shop.value?.description ?? 'Boutique en ligne propulsée par ShopAI',
  ogTitle: () => shop.value?.name ?? 'Boutique',
  ogDescription: () => shop.value?.description ?? '',
  ogImage: () => shop.value?.logo_url ?? undefined,
})

function selectCategory(value: string): void {
  category.value = value
  page.value = 1
}

function applySearch(): void {
  search.value = searchDraft.value.trim()
  page.value = 1
}

function addToCart(product: Product): void {
  add(product)
  toast.success(`« ${product.name} » ajouté au panier.`)
}
</script>

<template>
  <div class="container py-4">
    <AppState v-if="shopStatus === 'pending'" loading text="Ouverture de la boutique…" />
    <AppState v-else-if="shopError || !shop" variant="error" title="Boutique introuvable" text="Cette boutique n’existe pas ou est momentanément indisponible.">
      <NuxtLink to="/" class="btn btn-primary">Découvrir ShopAI</NuxtLink>
    </AppState>
    <template v-else>
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
              <button type="button" class="nav-link text-nowrap" :class="{ active: !category }" :aria-pressed="!category" @click="selectCategory('')">Tout</button>
            </li>
            <li v-for="item in shop.categories" :key="item.id" class="nav-item">
              <button type="button" class="nav-link text-nowrap" :class="{ active: category === item.slug }" :aria-pressed="category === item.slug" @click="selectCategory(item.slug)">
                {{ item.name }}
              </button>
            </li>
          </ul>
          <form class="ms-lg-auto" role="search" style="min-width: min(100%, 320px);" @submit.prevent="applySearch">
            <label for="shop-search" class="visually-hidden">Rechercher un produit</label>
            <div class="input-group">
              <input id="shop-search" v-model="searchDraft" type="search" class="form-control" placeholder="Rechercher un produit…">
              <button type="submit" class="btn btn-primary" aria-label="Rechercher"><i class="bi bi-search" aria-hidden="true" /></button>
            </div>
          </form>
        </div>

        <AppState v-if="status === 'pending'" loading text="Chargement des produits…" />
        <AppState v-else-if="error" variant="error" title="Impossible de charger les produits" text="Vérifiez votre connexion et réessayez.">
          <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
        </AppState>
        <AppState
          v-else-if="!products?.data.length"
          icon="bi-search"
          :title="search || category ? 'Aucun produit ne correspond' : 'La boutique se prépare'"
          :text="search || category ? 'Essayez une autre recherche ou catégorie.' : 'Les premiers produits arrivent très bientôt.'"
        />
        <template v-else>
          <div v-if="theme.layout === 'masonry'" class="masonry">
            <div v-for="product in products.data" :key="product.id">
              <ProductCard :product="product" :shop-slug="slug" :color="theme.primary" addable @add="addToCart" />
            </div>
          </div>
          <div v-else class="row g-4" :class="theme.layout === 'list' ? 'row-cols-1 row-cols-lg-2' : 'row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4'">
            <div v-for="product in products.data" :key="product.id" class="col">
              <ProductCard :product="product" :shop-slug="slug" :layout="theme.layout" :color="theme.primary" addable @add="addToCart" />
            </div>
          </div>
          <AppPagination v-model:page="page" :meta="products.meta" />
        </template>
      </section>
    </template>
  </div>
</template>

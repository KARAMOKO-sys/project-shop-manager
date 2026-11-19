<script setup lang="ts">
import type { Component } from 'vue'
import type { Paginated, Product, ShopFormat } from '~/types'
import FormatCatalog from '~/components/storefront/formats/FormatCatalog.vue'
import FormatClassic from '~/components/storefront/formats/FormatClassic.vue'
import FormatEditorial from '~/components/storefront/formats/FormatEditorial.vue'
import FormatMenu from '~/components/storefront/formats/FormatMenu.vue'
import FormatShowcase from '~/components/storefront/formats/FormatShowcase.vue'
import FormatSingleProduct from '~/components/storefront/formats/FormatSingleProduct.vue'

definePageMeta({ layout: 'storefront' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const slug = String(route.params.slug)

const { data: shop, status: shopStatus, error: shopError } = await useStorefrontShop(slug)
const { add } = useCart(slug)

const category = ref(typeof route.query.categorie === 'string' ? route.query.categorie : '')
const search = ref(typeof route.query.q === 'string' ? route.query.q : '')
const page = ref(1)

const { data: products, status, error, refresh } = await useAsyncData(
  `storefront-products-${slug}`,
  () => api<Paginated<Product>>(`/storefront/${slug}/products`, {
    query: { category: category.value || undefined, search: search.value || undefined, page: page.value },
  }),
  { watch: [category, search, page] },
)

/** Page structure chosen by the merchant in "Apparence"; every format shares the same props and events. */
const FORMATS: Record<ShopFormat, Component> = {
  classic: FormatClassic,
  editorial: FormatEditorial,
  catalog: FormatCatalog,
  menu: FormatMenu,
  showcase: FormatShowcase,
  single_product: FormatSingleProduct,
}

const theme = computed(() => resolveTheme(shop.value?.theme))
const formatComponent = computed(() => FORMATS[theme.value.format] ?? FormatClassic)

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

function applySearch(term: string): void {
  search.value = term
  page.value = 1
}

function addToCart(product: Product, quantity = 1): void {
  add(product, quantity)
  toast.success(`« ${product.name} » ajouté au panier.`)
}
</script>

<template>
  <div>
    <div v-if="shopStatus === 'pending' || shopError || !shop" class="container py-4">
      <AppState v-if="shopStatus === 'pending'" loading text="Ouverture de la boutique…" />
      <AppState v-else variant="error" title="Boutique introuvable" text="Cette boutique n’existe pas ou est momentanément indisponible.">
        <NuxtLink to="/" class="btn btn-primary">Découvrir la plateforme</NuxtLink>
      </AppState>
    </div>
    <component
      :is="formatComponent"
      v-else
      v-model:page="page"
      :shop="shop"
      :theme="theme"
      :products="products?.data ?? []"
      :meta="products?.meta"
      :loading="status === 'pending'"
      :failed="Boolean(error)"
      :category="category"
      :search="search"
      @select-category="selectCategory"
      @search="applySearch"
      @add="addToCart"
      @retry="refresh()"
    />
  </div>
</template>

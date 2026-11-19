<script setup lang="ts">
import type { PaginationMeta, Product, Shop, Theme } from '~/types'

/** Menu storefront (restaurants, producers): products grouped by category like a printed menu. */
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

const activeSection = ref('')

/** Sections in the shop's category order, then the uncategorised products. */
const sections = computed(() => {
  const order = new Map((props.shop.categories ?? []).map((item, index) => [item.slug, index]))
  const groups = new Map<string, { slug: string, name: string, products: Product[] }>()

  for (const product of props.products) {
    const slug = product.category?.slug ?? 'autres'
    const group = groups.get(slug) ?? { slug, name: product.category?.name ?? 'Autres', products: [] }
    group.products.push(product)
    groups.set(slug, group)
  }

  return [...groups.values()].sort((a, b) => (order.get(a.slug) ?? 999) - (order.get(b.slug) ?? 999))
})

function sectionId(slug: string): string {
  return `rubrique-${slug}`
}
</script>

<template>
  <div>
    <header class="sf-menu-head container">
      <p class="sf-label mb-2 text-muted">La carte</p>
      <h1 id="shop-title" class="display-5 fw-bold mb-2">{{ shop.name }}</h1>
      <p v-if="shop.tagline" class="lead mb-0 text-muted mx-auto" style="max-width: 36rem;">{{ shop.tagline }}</p>
    </header>

    <nav v-if="sections.length > 1" class="sf-menu-tabs" aria-label="Rubriques de la carte">
      <div class="container">
        <ul>
          <li v-for="section in sections" :key="section.slug">
            <a :href="`#${sectionId(section.slug)}`" :class="{ 'is-active': activeSection === section.slug }" @click="activeSection = section.slug">{{ section.name }}</a>
          </li>
        </ul>
      </div>
    </nav>

    <div id="catalogue" class="container py-4" style="max-width: 860px;">
      <StorefrontSearchForm :search="search" class="mb-4" @search="emit('search', $event)" />
      <StorefrontCatalogState :loading="loading" :failed="failed" :empty="!products.length" :filtered="Boolean(search || category)" @retry="emit('retry')">
        <section
          v-for="section in sections"
          :id="sectionId(section.slug)"
          :key="section.slug"
          class="sf-menu-section mb-5"
          :aria-labelledby="`${sectionId(section.slug)}-title`"
        >
          <h2 :id="`${sectionId(section.slug)}-title`" class="sf-menu-section-title h3 mb-3">{{ section.name }}</h2>
          <article v-for="product in section.products" :key="product.id" class="sf-menu-row">
            <div v-if="product.image_url" class="sf-menu-thumb">
              <ProductImage :src="product.image_url" :alt="product.name" :color="theme.primary" ratio="1 / 1" />
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="sf-menu-line">
                <h3 class="h6 mb-0">
                  <NuxtLink :to="`/boutique/${shop.slug}/produit/${product.slug}`" class="sf-menu-name">{{ product.name }}</NuxtLink>
                </h3>
                <span class="sf-leader" aria-hidden="true" />
                <PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" />
              </div>
              <p v-if="product.description" class="sf-menu-desc">{{ product.description }}</p>
            </div>
            <button
              v-if="product.stock_quantity > 0"
              type="button"
              class="btn btn-primary sf-round-add"
              :aria-label="`Ajouter ${product.name} au panier`"
              @click="emit('add', product)"
            >
              <i class="bi bi-plus-lg" aria-hidden="true" />
            </button>
            <span v-else class="badge text-bg-secondary">Épuisé</span>
          </article>
        </section>
        <AppPagination v-model:page="page" :meta="meta" />
      </StorefrontCatalogState>
    </div>
  </div>
</template>

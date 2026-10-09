<script setup lang="ts">
import type { Product, ProductLayout } from '~/types'

const props = withDefaults(defineProps<{
  product: Product
  shopSlug?: string
  layout?: ProductLayout
  showShop?: boolean
  addable?: boolean
  color?: string
}>(), {
  layout: 'grid',
})

const emit = defineEmits<{ add: [product: Product] }>()

const slug = computed(() => props.shopSlug ?? props.product.shop?.slug)
const link = computed(() => (slug.value ? `/boutique/${slug.value}/produit/${props.product.slug}` : undefined))
const outOfStock = computed(() => props.product.stock_quantity <= 0)
</script>

<template>
  <article class="card product-card card-hover h-100 overflow-hidden" :class="{ 'is-list': layout === 'list' }">
    <ProductImage :src="product.image_url" :alt="product.name" :color="color" :ratio="layout === 'list' ? '1 / 1' : '4 / 3'" />
    <div class="card-body d-flex flex-column gap-1">
      <p v-if="showShop && product.shop" class="small text-muted mb-0">
        <i class="bi bi-shop me-1" aria-hidden="true" />{{ product.shop.name }}
      </p>
      <h3 class="card-title mb-1">
        <NuxtLink v-if="link" :to="link" class="stretched-link text-reset text-decoration-none">{{ product.name }}</NuxtLink>
        <template v-else>{{ product.name }}</template>
      </h3>
      <p v-if="layout === 'list' && product.description" class="small text-muted mb-2">
        {{ product.description.slice(0, 140) }}{{ product.description.length > 140 ? '…' : '' }}
      </p>
      <span class="price-accent mt-auto" aria-hidden="true" />
      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" />
        <span v-if="outOfStock" class="badge text-bg-secondary">Épuisé</span>
        <button
          v-else-if="addable"
          type="button"
          class="btn btn-sm btn-primary add-btn"
          :aria-label="`Ajouter ${product.name} au panier`"
          @click="emit('add', product)"
        >
          <i class="bi bi-bag-plus" aria-hidden="true" /> Ajouter
        </button>
      </div>
    </div>
  </article>
</template>

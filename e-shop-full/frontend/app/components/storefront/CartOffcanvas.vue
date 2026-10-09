<script setup lang="ts">
const props = defineProps<{ shopSlug: string, currency: string, color?: string }>()

const { items, subtotal, setQuantity, remove } = useCart(props.shopSlug)
</script>

<template>
  <div id="cartOffcanvas" class="offcanvas offcanvas-end" tabindex="-1" aria-labelledby="cartOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
      <h2 id="cartOffcanvasLabel" class="offcanvas-title h5 fw-bold">
        <i class="bi bi-bag me-1" aria-hidden="true" /> Mon panier
      </h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer le panier" />
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <AppState
        v-if="!items.length"
        icon="bi-bag"
        title="Votre panier est vide"
        text="Parcourez la boutique et ajoutez vos coups de cœur."
      />
      <ul v-else class="list-unstyled d-grid gap-3 mb-4">
        <li v-for="item in items" :key="item.product_id" class="d-flex gap-3 align-items-center">
          <div class="table-thumb" style="width: 4rem;">
            <ProductImage :src="item.image_url" :alt="item.name" :color="color" ratio="1 / 1" />
          </div>
          <div class="flex-grow-1 min-w-0">
            <NuxtLink :to="`/boutique/${shopSlug}/produit/${item.slug}`" class="fw-semibold text-reset text-decoration-none d-block text-truncate">
              {{ item.name }}
            </NuxtLink>
            <PriceTag :amount="item.price * item.quantity" :currency="item.currency" class="small" />
            <div class="d-flex align-items-center gap-2 mt-1">
              <div class="input-group input-group-sm" style="width: 7rem;">
                <button type="button" class="btn btn-outline-secondary" :aria-label="`Retirer un ${item.name}`" @click="setQuantity(item.product_id, item.quantity - 1)">−</button>
                <span class="form-control text-center" aria-live="polite">{{ item.quantity }}</span>
                <button type="button" class="btn btn-outline-secondary" :aria-label="`Ajouter un ${item.name}`" @click="setQuantity(item.product_id, item.quantity + 1)">+</button>
              </div>
              <button type="button" class="btn btn-sm btn-link text-danger" :aria-label="`Supprimer ${item.name} du panier`" @click="remove(item.product_id)">
                <i class="bi bi-trash" aria-hidden="true" />
              </button>
            </div>
          </div>
        </li>
      </ul>
      <div v-if="items.length" class="mt-auto border-top pt-3">
        <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
          <span>Sous-total</span>
          <PriceTag :amount="subtotal" :currency="currency" />
        </div>
        <NuxtLink :to="`/boutique/${shopSlug}/commande`" class="btn btn-primary btn-lg w-100">
          Commander <i class="bi bi-arrow-right ms-1" aria-hidden="true" />
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

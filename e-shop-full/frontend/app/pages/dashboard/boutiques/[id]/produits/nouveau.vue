<script setup lang="ts">
import type { Product, Resource, Shop } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Nouveau produit' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = String(route.params.id)

const { data: shop } = useAsyncData(
  `shop-${shopId}`,
  () => api<Resource<Shop>>(`/shops/${shopId}`).then(response => response.data),
  { server: false },
)

async function onSaved(product: Product): Promise<void> {
  toast.success(`« ${product.name} » enregistré. Notre agent IA l’a analysé.`)
  await navigateTo(`/dashboard/boutiques/${shopId}/produits/${product.id}?cree=1`)
}
</script>

<template>
  <div style="max-width: 980px;">
    <nav aria-label="Fil d’Ariane">
      <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><NuxtLink to="/dashboard">Mes boutiques</NuxtLink></li>
        <li class="breadcrumb-item"><NuxtLink :to="`/dashboard/boutiques/${shopId}?onglet=produits`">{{ shop?.name ?? 'Boutique' }}</NuxtLink></li>
        <li class="breadcrumb-item active" aria-current="page">Nouveau produit</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-1">Nouveau produit</h1>
    <div class="alert alert-info d-flex gap-2 align-items-start my-4" role="note">
      <i class="bi bi-shield-check fs-5" aria-hidden="true" />
      <span>Notre agent IA vérifie chaque produit avant qu’il soit visible du public. C’est ce qui rend les boutiques ShopAI dignes de confiance.</span>
    </div>
    <DashboardProductForm :shop-id="shopId" :currency="shop?.currency ?? 'XOF'" :color="shop?.theme?.primary" @saved="onSaved" />
  </div>
</template>

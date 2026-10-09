<script setup lang="ts">
import type { Resource, Shop } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Mes boutiques' })

const api = useApi()
const { user } = useAuth()

const { data: shops, status, error, refresh } = useAsyncData(
  'my-shops',
  () => api<Resource<Shop[]>>('/shops').then(response => response.data),
  { server: false },
)

const loading = computed(() => status.value === 'pending' || status.value === 'idle')
</script>

<template>
  <div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
      <div>
        <h1 class="h3 fw-bold mb-1">Bonjour{{ user ? `, ${user.name}` : '' }} 👋</h1>
        <p class="text-muted mb-0">Gérez vos boutiques, vos produits et vos commandes.</p>
      </div>
      <NuxtLink to="/creer-ma-boutique" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" /> Nouvelle boutique
      </NuxtLink>
    </div>

    <AppState v-if="loading" loading text="Chargement de vos boutiques…" />
    <AppState v-else-if="error" variant="error" title="Impossible de charger vos boutiques" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <div v-else-if="!shops?.length" class="card">
      <AppState icon="bi-shop" title="Vous n’avez pas encore de boutique" text="Décrivez votre activité en une phrase et l’IA crée votre boutique en quelques minutes.">
        <NuxtLink to="/creer-ma-boutique" class="btn btn-primary btn-lg"><i class="bi bi-magic me-1" aria-hidden="true" />Créer ma boutique</NuxtLink>
      </AppState>
    </div>
    <div v-else class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
      <div v-for="shop in shops" :key="shop.id" class="col">
        <article class="card h-100 card-hover overflow-hidden">
          <div class="p-3 pb-0">
            <ThemePreview :theme="shop.theme" :title="shop.name" :name="shop.template" />
          </div>
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <h2 class="h5 fw-bold mb-1">{{ shop.name }}</h2>
              <span class="badge rounded-pill" :class="shop.status === 'active' ? 'text-bg-success' : 'text-bg-secondary'">{{ shop.status === 'active' ? 'En ligne' : shop.status }}</span>
            </div>
            <p class="small text-muted">{{ shop.tagline ?? shop.description ?? '—' }}</p>
            <p class="small mb-3">
              <i class="bi bi-box-seam me-1" aria-hidden="true" />{{ shop.products_count ?? 0 }} produit(s)
              <span class="mx-2" aria-hidden="true">·</span>{{ shop.currency }}
            </p>
            <div class="d-flex gap-2 mt-auto">
              <NuxtLink :to="`/dashboard/boutiques/${shop.id}`" class="btn btn-primary flex-grow-1">
                <i class="bi bi-sliders me-1" aria-hidden="true" />Gérer
              </NuxtLink>
              <NuxtLink :to="`/boutique/${shop.slug}`" class="btn btn-outline-primary" :aria-label="`Voir la boutique ${shop.name}`">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true" />
              </NuxtLink>
            </div>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

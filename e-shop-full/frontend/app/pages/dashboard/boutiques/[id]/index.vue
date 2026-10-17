<script setup lang="ts">
import type { Resource, Shop } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const TABS = [
  { key: 'apercu', label: 'Vue d’ensemble', icon: 'bi-speedometer2' },
  { key: 'produits', label: 'Produits', icon: 'bi-box-seam' },
  { key: 'commandes', label: 'Commandes', icon: 'bi-receipt' },
  { key: 'promotions', label: 'Promotions', icon: 'bi-ticket-perforated' },
  { key: 'apparence', label: 'Apparence', icon: 'bi-palette' },
  { key: 'parametres', label: 'Paramètres', icon: 'bi-gear' },
] as const

type TabKey = typeof TABS[number]['key']

const route = useRoute()
const router = useRouter()
const api = useApi()
const shopId = computed(() => String(route.params.id))

const { data: shop, status, error, refresh } = useAsyncData(
  () => `shop-${shopId.value}`,
  () => api<Resource<Shop>>(`/shops/${shopId.value}`).then(response => response.data),
  { server: false },
)

const activeTab = computed<TabKey>({
  get: () => TABS.find(tab => tab.key === route.query.onglet)?.key ?? 'apercu',
  set: value => void router.replace({ query: { ...route.query, onglet: value } }),
})

useSeoMeta({ title: () => shop.value?.name ?? 'Ma boutique' })

function onShopUpdated(updated: Shop): void {
  shop.value = updated
}

/** On narrow screens the tab bar scrolls: keep the active tab visible. */
function revealActiveTab(): void {
  nextTick(() => document.getElementById(`tab-${activeTab.value}`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' }))
}

watch(activeTab, revealActiveTab)
onMounted(revealActiveTab)

function onTabKeydown(event: KeyboardEvent, index: number): void {
  const offset = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0

  if (offset) {
    const next = TABS[(index + offset + TABS.length) % TABS.length]!
    activeTab.value = next.key
    nextTick(() => document.getElementById(`tab-${next.key}`)?.focus())
  }
}
</script>

<template>
  <div>
    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement de la boutique…" />
    <AppState v-else-if="error || !shop" variant="error" title="Boutique introuvable" :text="errorMessage(error, 'Cette boutique n’existe pas ou ne vous appartient pas.')">
      <div class="d-flex gap-2 justify-content-center">
        <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
        <NuxtLink to="/dashboard" class="btn btn-light">Mes boutiques</NuxtLink>
      </div>
    </AppState>
    <template v-else>
      <nav aria-label="Fil d’Ariane">
        <ol class="breadcrumb small mb-2">
          <li class="breadcrumb-item"><NuxtLink to="/dashboard">Mes boutiques</NuxtLink></li>
          <li class="breadcrumb-item active" aria-current="page">{{ shop.name }}</li>
        </ol>
      </nav>
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
          <span class="shop-logo-mark" :style="{ background: shop.theme?.primary, color: contrastText(shop.theme?.primary ?? '#0F3D2E') }" aria-hidden="true">
            <img v-if="shop.logo_url" :src="shop.logo_url" alt="">
            <template v-else>{{ shop.name.charAt(0).toUpperCase() }}</template>
          </span>
          <div>
            <h1 class="h3 fw-bold mb-0">{{ shop.name }}</h1>
            <p class="text-muted small mb-0">{{ shop.tagline }}</p>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <NuxtLink :to="`/dashboard/boutiques/${shop.id}/produits/nouveau`" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true" />Ajouter un produit
          </NuxtLink>
          <NuxtLink :to="`/boutique/${shop.slug}`" class="btn btn-outline-primary" target="_blank">
            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true" />Voir ma boutique
          </NuxtLink>
        </div>
      </div>

      <ul class="nav nav-tabs dashboard-tabs flex-nowrap overflow-auto mb-4" role="tablist" aria-label="Gestion de la boutique">
        <li v-for="(tab, index) in TABS" :key="tab.key" class="nav-item" role="presentation">
          <button
            :id="`tab-${tab.key}`"
            type="button"
            class="nav-link text-nowrap"
            :class="{ active: activeTab === tab.key }"
            role="tab"
            :aria-selected="activeTab === tab.key"
            :aria-controls="`panel-${tab.key}`"
            :tabindex="activeTab === tab.key ? 0 : -1"
            @click="activeTab = tab.key"
            @keydown="onTabKeydown($event, index)"
          >
            <i class="bi me-1" :class="tab.icon" aria-hidden="true" />{{ tab.label }}
          </button>
        </li>
      </ul>

      <div :id="`panel-${activeTab}`" role="tabpanel" :aria-labelledby="`tab-${activeTab}`" tabindex="0">
        <DashboardShopOverview v-if="activeTab === 'apercu'" :shop="shop" />
        <DashboardShopProducts v-else-if="activeTab === 'produits'" :shop="shop" />
        <DashboardShopOrders v-else-if="activeTab === 'commandes'" :shop="shop" />
        <DashboardShopCoupons v-else-if="activeTab === 'promotions'" :shop="shop" />
        <DashboardShopAppearance v-else-if="activeTab === 'apparence'" :shop="shop" @updated="onShopUpdated" />
        <DashboardShopSettings v-else :shop="shop" @updated="onShopUpdated" />
      </div>
    </template>
  </div>
</template>

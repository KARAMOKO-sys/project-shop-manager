<script setup lang="ts">
import type { Analytics, Resource, Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const api = useApi()

const { data: analytics, status, error, refresh } = useAsyncData(
  `analytics-${props.shop.id}`,
  () => api<Resource<Analytics>>(`/shops/${props.shop.id}/analytics`).then(response => response.data),
  { server: false },
)

const kpis = computed(() => {
  const stats = analytics.value

  if (!stats) {
    return []
  }

  return [
    { label: 'Chiffre d’affaires', value: formatPrice(stats.revenue, props.shop.currency), icon: 'bi-cash-stack', highlight: true },
    { label: 'Commandes', value: formatNumber(stats.orders), icon: 'bi-receipt' },
    { label: 'Clients', value: formatNumber(stats.customers), icon: 'bi-people' },
    { label: 'Panier moyen', value: formatPrice(stats.average_order_value, props.shop.currency), icon: 'bi-basket' },
    { label: 'Produits', value: formatNumber(stats.products), icon: 'bi-box-seam' },
    { label: 'En vérification', value: formatNumber(stats.pending_moderation), icon: 'bi-shield-exclamation' },
  ]
})

const lowStockProducts = computed(() => analytics.value?.low_stock_products ?? [])
const revenueDays = computed(() => analytics.value?.revenue_by_day ?? [])

const statusRows = computed(() => {
  const counts = analytics.value?.orders_by_status ?? {}
  const total = Object.values(counts).reduce((sum, count) => sum + Number(count ?? 0), 0)

  return ORDER_STATUSES.map(item => ({
    ...item,
    count: Number(counts[item.value] ?? 0),
    percent: total ? Math.round((Number(counts[item.value] ?? 0) / total) * 100) : 0,
  }))
})
</script>

<template>
  <div>
    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Calcul de vos statistiques…" />
    <AppState v-else-if="error || !analytics" variant="error" title="Statistiques indisponibles" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <template v-else>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 mb-4">
        <div v-for="kpi in kpis" :key="kpi.label" class="col">
          <div class="card kpi-card h-100 p-4 d-flex flex-row align-items-center gap-3">
            <span class="icon-tile" :class="{ 'is-gold': kpi.highlight }" aria-hidden="true"><i class="bi" :class="kpi.icon" /></span>
            <div>
              <p class="small text-muted mb-1 fw-medium">{{ kpi.label }}</p>
              <p class="kpi-value mb-0">{{ kpi.value }}</p>
            </div>
          </div>
        </div>
      </div>
      <div v-if="analytics.pending_moderation > 0" class="alert alert-warning d-flex gap-2" role="status">
        <i class="bi bi-shield-exclamation" aria-hidden="true" />
        <span>{{ analytics.pending_moderation }} produit(s) en cours de vérification par notre agent IA. Ils seront visibles du public dès validation.</span>
      </div>
      <div v-if="lowStockProducts.length" class="card border-warning-subtle p-4 mb-4" role="region" aria-labelledby="low-stock-title">
        <h2 id="low-stock-title" class="h5 fw-bold mb-1">
          <i class="bi bi-exclamation-triangle text-warning me-1" aria-hidden="true" />Stock faible
        </h2>
        <p class="small text-muted mb-3">Réapprovisionnez ces produits avant qu’ils ne soient épuisés.</p>
        <ul class="list-group list-group-flush">
          <li v-for="product in lowStockProducts" :key="product.id" class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
            <span class="fw-semibold text-truncate">{{ product.name }}</span>
            <span class="d-flex align-items-center gap-2 flex-shrink-0">
              <span class="badge rounded-pill" :class="product.stock_quantity <= 0 ? 'text-bg-danger' : 'text-bg-warning'">
                {{ product.stock_quantity <= 0 ? 'Épuisé' : `${formatNumber(product.stock_quantity)} restant(s)` }}
              </span>
              <NuxtLink :to="`/dashboard/boutiques/${shop.id}/produits/${product.id}`" class="btn btn-sm btn-light" :aria-label="`Modifier ${product.name}`">
                <i class="bi bi-pencil" aria-hidden="true" />
              </NuxtLink>
            </span>
          </li>
        </ul>
      </div>
      <div v-if="revenueDays.length" class="card p-4 mb-4">
        <DashboardRevenueChart :days="revenueDays" :currency="shop.currency" />
      </div>
      <div class="card p-4">
        <h2 class="h5 fw-bold mb-3">Commandes par statut</h2>
        <ul class="list-unstyled d-grid gap-3 mb-0">
          <li v-for="row in statusRows" :key="row.value">
            <div class="d-flex justify-content-between small mb-1">
              <span class="fw-semibold">{{ row.label }}</span>
              <span>{{ row.count }}</span>
            </div>
            <div class="progress" role="progressbar" :aria-label="`Commandes ${row.label}`" :aria-valuenow="row.percent" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
              <div class="progress-bar" :class="`bg-${row.variant}`" :style="{ width: `${row.percent}%` }" />
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

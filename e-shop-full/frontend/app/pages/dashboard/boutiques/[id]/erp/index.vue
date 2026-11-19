<script setup lang="ts">
import type { ErpDashboard, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const route = useRoute()
const api = useApi()
const { shop, loading, error, refresh, basePath, apiBase } = useErpShop(() => String(route.params.id))

const PERIODS = [7, 30, 90, 365] as const
const days = ref<number>(30)

const { data: dashboard, status, error: dashboardError, refresh: refreshDashboard } = useAsyncData(
  () => `erp-dashboard-${route.params.id}-${days.value}`,
  () => api<Resource<ErpDashboard>>(`${apiBase.value}/dashboard?days=${days.value}`).then(response => response.data),
  { server: false, watch: [days] },
)

useSeoMeta({ title: () => `Gestion d’entreprise — ${shop.value?.name ?? ''}` })

const currency = computed(() => dashboard.value?.currency ?? shop.value?.currency ?? 'XOF')
const money = (value: number | undefined) => formatPrice(value ?? 0, currency.value)

const salesKpis = computed(() => {
  const sales = dashboard.value?.sales

  if (!sales) {
    return []
  }

  return [
    { label: 'Chiffre d’affaires', value: money(sales.revenue), icon: 'bi-cash-stack', highlight: true },
    { label: 'Commandes', value: formatNumber(sales.orders_count), icon: 'bi-receipt' },
    { label: 'Panier moyen', value: money(sales.average_order), icon: 'bi-basket' },
    { label: 'Marge brute', value: money(sales.gross_margin), icon: 'bi-graph-up-arrow' },
  ]
})

const marginRate = computed(() => {
  const sales = dashboard.value?.sales

  return sales && sales.revenue > 0 ? Math.round((sales.gross_margin / sales.revenue) * 100) : null
})

const expenseRows = computed(() => {
  const byCategory = dashboard.value?.expenses?.by_category ?? {}
  const total = dashboard.value?.expenses?.total ?? 0

  return Object.entries(byCategory)
    .map(([category, amount]) => ({ category, label: expenseCategoryLabel(category), amount: Number(amount ?? 0), percent: total > 0 ? Math.round((Number(amount ?? 0) / total) * 100) : 0 }))
    .sort((a, b) => b.amount - a.amount)
})

const openStages = computed(() => (dashboard.value?.crm?.stages ?? []).filter(stage => !['won', 'lost'].includes(stage.stage)))
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" title="Tableau de bord" subtitle="Toute votre entreprise sur un seul écran." icon="bi-speedometer2" @retry="refresh()">
    <template #actions>
      <div class="btn-group" role="group" aria-label="Période analysée">
        <button v-for="period in PERIODS" :key="period" type="button" class="btn btn-sm" :class="days === period ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="days === period" @click="days = period">
          {{ period === 365 ? '1 an' : `${period} j` }}
        </button>
      </div>
    </template>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Calcul de vos indicateurs…" />
    <AppState v-else-if="dashboardError || !dashboard" variant="error" title="Indicateurs indisponibles" :text="errorMessage(dashboardError)">
      <button type="button" class="btn btn-primary" @click="refreshDashboard()">Réessayer</button>
    </AppState>
    <template v-else>
      <section aria-labelledby="sales-title" class="mb-4">
        <h2 id="sales-title" class="h6 text-uppercase text-muted fw-bold mb-3">Ventes — {{ dashboard.period_days }} derniers jours</h2>
        <div class="row row-cols-2 row-cols-xl-4 g-2 g-sm-3">
          <div v-for="kpi in salesKpis" :key="kpi.label" class="col">
            <div class="card kpi-card h-100 p-3 d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2 gap-md-3">
              <span class="icon-tile" :class="{ 'is-gold': kpi.highlight }" aria-hidden="true"><i class="bi" :class="kpi.icon" /></span>
              <div class="min-w-0">
                <p class="small text-muted mb-1 fw-medium">{{ kpi.label }}</p>
                <p class="kpi-value mb-0">{{ kpi.value }}</p>
              </div>
            </div>
          </div>
        </div>
        <p class="small text-muted mt-2 mb-0">
          Coût des marchandises vendues : {{ money(dashboard.sales.cost_of_goods) }}<template v-if="marginRate !== null"> · taux de marge {{ marginRate }} %</template>
          · <NuxtLink :to="`/dashboard/boutiques/${shop!.id}?onglet=commandes`">{{ dashboard.sales.to_process }} commande(s) à traiter</NuxtLink>
        </p>
      </section>

      <div v-if="dashboard.expenses" class="card p-4 mb-4 border-0" :class="dashboard.expenses.net_profit >= 0 ? 'bg-success-subtle' : 'bg-danger-subtle'" role="region" aria-labelledby="profit-title">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
          <div>
            <h2 id="profit-title" class="h6 fw-bold mb-1">Bénéfice net estimé</h2>
            <p class="small mb-0">Chiffre d’affaires − coût des marchandises − dépenses de la période.</p>
          </div>
          <p class="display-6 fw-bold mb-0">{{ money(dashboard.expenses.net_profit) }}</p>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div v-if="dashboard.inventory" class="col-md-6 col-xl-3">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold"><i class="bi bi-boxes me-1" aria-hidden="true" />Inventaire</h2>
            <p class="kpi-value mb-1">{{ money(dashboard.inventory.stock_value) }}</p>
            <p class="small text-muted">Valeur du stock au coût · {{ dashboard.inventory.warehouses_count }} entrepôt(s)</p>
            <ul class="list-unstyled small mb-3">
              <li><span class="badge text-bg-danger me-1">{{ dashboard.inventory.critical_count }}</span>réassort urgent</li>
              <li><span class="badge text-bg-warning me-1">{{ dashboard.inventory.reorder_count }}</span>produit(s) à commander</li>
              <li><span class="badge text-bg-secondary me-1">{{ dashboard.inventory.out_of_stock_count }}</span>en rupture</li>
            </ul>
            <NuxtLink :to="`${basePath}/inventaire?onglet=reassort`" class="btn btn-sm btn-outline-primary mt-auto">Voir les suggestions</NuxtLink>
          </div>
        </div>
        <div v-if="dashboard.purchase" class="col-md-6 col-xl-3">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold"><i class="bi bi-truck me-1" aria-hidden="true" />Achats</h2>
            <p class="kpi-value mb-1">{{ money(dashboard.purchase.open_amount) }}</p>
            <p class="small text-muted">{{ dashboard.purchase.open_count }} bon(s) de commande en attente de réception</p>
            <p v-if="dashboard.purchase.late_count" class="small text-danger"><i class="bi bi-clock-history me-1" aria-hidden="true" />{{ dashboard.purchase.late_count }} en retard</p>
            <p class="small text-muted mb-3">{{ dashboard.purchase.suppliers_count }} fournisseur(s)</p>
            <NuxtLink :to="`${basePath}/achats`" class="btn btn-sm btn-outline-primary mt-auto">Gérer les achats</NuxtLink>
          </div>
        </div>
        <div v-if="dashboard.invoicing" class="col-md-6 col-xl-3">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold"><i class="bi bi-receipt-cutoff me-1" aria-hidden="true" />Facturation</h2>
            <p class="kpi-value mb-1">{{ money(dashboard.invoicing.receivables) }}</p>
            <p class="small text-muted">Créances clients à encaisser</p>
            <p v-if="dashboard.invoicing.overdue_count" class="small text-danger"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true" />{{ money(dashboard.invoicing.overdue_amount) }} en retard ({{ dashboard.invoicing.overdue_count }})</p>
            <p class="small text-muted mb-3">Facturé : {{ money(dashboard.invoicing.invoiced) }} · encaissé : {{ money(dashboard.invoicing.collected) }}</p>
            <NuxtLink :to="`${basePath}/factures`" class="btn btn-sm btn-outline-primary mt-auto">Voir les factures</NuxtLink>
          </div>
        </div>
        <div v-if="dashboard.crm" class="col-md-6 col-xl-3">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold"><i class="bi bi-kanban me-1" aria-hidden="true" />CRM</h2>
            <p class="kpi-value mb-1">{{ money(dashboard.crm.weighted_forecast) }}</p>
            <p class="small text-muted">Prévision pondérée · {{ dashboard.crm.open_count }} opportunité(s) ouverte(s)</p>
            <ul class="list-unstyled small mb-3">
              <li v-for="stage in openStages" :key="stage.stage">{{ stage.label }} : {{ stage.count }} · {{ money(stage.expected_revenue) }}</li>
              <li v-if="dashboard.crm.win_rate !== null" class="fw-semibold mt-1">Taux de réussite : {{ formatNumber(dashboard.crm.win_rate, 1) }} %</li>
            </ul>
            <NuxtLink :to="`${basePath}/crm`" class="btn btn-sm btn-outline-primary mt-auto">Ouvrir le pipeline</NuxtLink>
          </div>
        </div>
        <div v-if="!dashboard.inventory || !dashboard.invoicing || !dashboard.crm || !dashboard.purchase || !dashboard.expenses" class="col-md-6 col-xl-3">
          <div class="card h-100 p-4 border-primary-subtle text-center d-flex flex-column justify-content-center">
            <i class="bi bi-grid-3x3-gap fs-2 text-primary" aria-hidden="true" />
            <h2 class="h6 fw-bold mt-2">Ajoutez des applications</h2>
            <p class="small text-muted">Inventaire, achats, facturation, dépenses, CRM : activez seulement ce dont vous avez besoin.</p>
            <NuxtLink :to="`${basePath}/applications`" class="btn btn-sm btn-primary">Catalogue des applications</NuxtLink>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div v-if="dashboard.expenses" class="col-lg-6">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-wallet2 me-1" aria-hidden="true" />Dépenses : {{ money(dashboard.expenses.total) }}</h2>
            <p class="small text-muted mb-3">Répartition par catégorie</p>
            <ul v-if="expenseRows.length" class="list-unstyled d-grid gap-3 mb-0">
              <li v-for="row in expenseRows" :key="row.category">
                <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold">{{ row.label }}</span><span>{{ money(row.amount) }}</span></div>
                <div class="progress" role="progressbar" :aria-label="`Part des dépenses ${row.label}`" :aria-valuenow="row.percent" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
                  <div class="progress-bar bg-warning" :style="{ width: `${row.percent}%` }" />
                </div>
              </li>
            </ul>
            <p v-else class="small text-muted mb-0">Aucune dépense enregistrée sur la période.</p>
          </div>
        </div>
        <div :class="dashboard.expenses ? 'col-lg-6' : 'col-12'">
          <div class="card h-100 p-4">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-1" aria-hidden="true" />Activité récente</h2>
            <ErpActivityFeed :activities="dashboard.recent_activity" />
          </div>
        </div>
      </div>
    </template>
  </ErpPage>
</template>

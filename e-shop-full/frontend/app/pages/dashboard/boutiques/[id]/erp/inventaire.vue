<script setup lang="ts">
import type { ErpPaginated, PurchaseOrder, ReorderSuggestion, Resource, StockMovement, StockRow, Warehouse } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const TABS = [
  { key: 'stock', label: 'Stock', icon: 'bi-box-seam' },
  { key: 'mouvements', label: 'Mouvements', icon: 'bi-arrow-left-right' },
  { key: 'reassort', label: 'Réapprovisionnement', icon: 'bi-lightbulb' },
  { key: 'entrepots', label: 'Entrepôts', icon: 'bi-building' },
] as const
type TabKey = typeof TABS[number]['key']

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { shop, loading, error, refresh, hasModule, isInstalled, basePath, apiBase } = useErpShop(() => String(route.params.id), 'inventory')

useSeoMeta({ title: 'Inventaire' })

const activeTab = computed<TabKey>({
  get: () => TABS.find(tab => tab.key === route.query.onglet)?.key ?? 'stock',
  set: value => void router.replace({ query: { ...route.query, onglet: value } }),
})
const currency = computed(() => shop.value?.currency ?? 'XOF')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)

/* Warehouses (shared by every tab) */
const { data: warehouses, refresh: refreshWarehouses } = useAsyncData(
  () => `erp-warehouses-${route.params.id}`,
  () => (enabled.value ? api<Resource<Warehouse[]>>(`${apiBase.value}/warehouses`).then(response => response.data) : Promise.resolve([] as Warehouse[])),
  { server: false, watch: [enabled] },
)

/* Stock */
const stockPage = ref(1)
const stockSearch = ref('')
const stockWarehouse = ref<number | ''>('')
const debouncedSearch = ref('')
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(stockSearch, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value
    stockPage.value = 1
  }, 350)
})

const { data: stock, status: stockStatus, error: stockError, refresh: refreshStock } = useAsyncData(
  () => `erp-stock-${route.params.id}`,
  () => (enabled.value
    ? api<ErpPaginated<StockRow>>(`${apiBase.value}/inventory/stock${queryString({ page: stockPage.value, search: debouncedSearch.value, warehouse_id: stockWarehouse.value })}`)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, stockPage, debouncedSearch, stockWarehouse] },
)

/* Movements */
const movementPage = ref(1)
const movementType = ref('')
const movementWarehouse = ref<number | ''>('')
const { data: movements, status: movementStatus, error: movementError, refresh: refreshMovements } = useAsyncData(
  () => `erp-movements-${route.params.id}`,
  () => (enabled.value && activeTab.value === 'mouvements'
    ? api<ErpPaginated<StockMovement>>(`${apiBase.value}/inventory/movements${queryString({ page: movementPage.value, type: movementType.value, warehouse_id: movementWarehouse.value })}`)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, activeTab, movementPage, movementType, movementWarehouse] },
)
watch([movementType, movementWarehouse], () => (movementPage.value = 1))

const MOVEMENT_TYPES = [
  { value: 'opening', label: 'Stock d’ouverture' },
  { value: 'receipt', label: 'Réception fournisseur' },
  { value: 'sale', label: 'Vente' },
  { value: 'sale_return', label: 'Retour / annulation' },
  { value: 'adjustment', label: 'Inventaire / ajustement' },
  { value: 'transfer_in', label: 'Transfert entrant' },
  { value: 'transfer_out', label: 'Transfert sortant' },
]

/* Suggestions */
const { data: suggestions, status: suggestionStatus, error: suggestionError, refresh: refreshSuggestions } = useAsyncData(
  () => `erp-suggestions-${route.params.id}`,
  () => (enabled.value && activeTab.value === 'reassort'
    ? api<Resource<ReorderSuggestion[]>>(`${apiBase.value}/inventory/reorder-suggestions`).then(response => response.data)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, activeTab] },
)
const selectedSuggestions = ref<number[]>([])
const creatingOrders = ref(false)

async function createPurchaseOrders(): Promise<void> {
  creatingOrders.value = true

  try {
    const response = await api<Resource<PurchaseOrder[]>>(`${apiBase.value}/purchase-orders/from-suggestions`, {
      method: 'POST',
      body: { product_ids: selectedSuggestions.value.length ? selectedSuggestions.value : undefined },
    })

    if (!response.data.length) {
      toast.error('Aucun bon créé : associez d’abord un fournisseur à ces produits (via un premier bon de commande).')

      return
    }

    toast.success(`${response.data.length} bon(s) de commande brouillon créé(s) : ${response.data.map(order => order.number).join(', ')}.`)
    await router.push(`${basePath.value}/achats?onglet=commandes`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La création des bons de commande a échoué.'))
  }
  finally {
    creatingOrders.value = false
  }
}

/* Product settings (reorder point & cost) */
const productModal = ref(false)
const productForm = reactive({ id: 0, name: '', reorder_point: null as number | null, cost_price: null as number | null })
const productErrors = ref<Record<string, string>>({})
const savingProduct = ref(false)

function editProduct(row: StockRow): void {
  Object.assign(productForm, { id: row.id, name: row.name, reorder_point: row.reorder_point, cost_price: row.cost_price })
  productErrors.value = {}
  productModal.value = true
}

async function saveProduct(): Promise<void> {
  savingProduct.value = true
  productErrors.value = {}

  try {
    await api(`${apiBase.value}/inventory/products/${productForm.id}`, {
      method: 'PATCH',
      body: { reorder_point: productForm.reorder_point === null || String(productForm.reorder_point) === '' ? null : productForm.reorder_point, cost_price: productForm.cost_price === null || String(productForm.cost_price) === '' ? null : productForm.cost_price },
    })
    productModal.value = false
    toast.success(`Paramètres de stock de « ${productForm.name} » enregistrés.`)
    await refreshStock()
  }
  catch (requestError) {
    productErrors.value = validationErrors(requestError)

    if (!Object.keys(productErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingProduct.value = false
  }
}

/* Adjustment */
const adjustModal = ref(false)
const adjustForm = reactive({ product_id: 0, product_name: '', warehouse_id: 0, counted_quantity: 0 as number, note: '' })
const adjustErrors = ref<Record<string, string>>({})
const savingAdjust = ref(false)
const adjustCurrent = computed(() => stock.value?.data.find(row => row.id === adjustForm.product_id)?.warehouses.find(item => item.warehouse_id === adjustForm.warehouse_id)?.quantity ?? 0)

function openAdjust(row: StockRow): void {
  const defaultWarehouse = warehouses.value?.find(item => item.is_default) ?? warehouses.value?.[0]
  const warehouseId = defaultWarehouse?.id ?? 0
  Object.assign(adjustForm, {
    product_id: row.id,
    product_name: row.name,
    warehouse_id: warehouseId,
    counted_quantity: row.warehouses.find(item => item.warehouse_id === warehouseId)?.quantity ?? 0,
    note: '',
  })
  adjustErrors.value = {}
  adjustModal.value = true
}

async function saveAdjust(): Promise<void> {
  savingAdjust.value = true
  adjustErrors.value = {}

  try {
    const response = await api<Resource<StockMovement | null>>(`${apiBase.value}/inventory/adjustments`, {
      method: 'POST',
      body: { product_id: adjustForm.product_id, warehouse_id: adjustForm.warehouse_id, counted_quantity: adjustForm.counted_quantity, note: adjustForm.note || null },
    })
    adjustModal.value = false
    toast.success(response.data ? `Inventaire enregistré (${response.data.quantity > 0 ? '+' : ''}${response.data.quantity}).` : 'Aucun écart : le stock est déjà juste.')
    await refreshStock()
  }
  catch (requestError) {
    adjustErrors.value = validationErrors(requestError)

    if (!Object.keys(adjustErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingAdjust.value = false
  }
}

/* Transfer */
const transferModal = ref(false)
const transferForm = reactive({ product_id: 0, product_name: '', from_warehouse_id: 0, to_warehouse_id: 0, quantity: 1 as number, note: '' })
const transferErrors = ref<Record<string, string>>({})
const savingTransfer = ref(false)

function openTransfer(row: StockRow): void {
  const list = warehouses.value ?? []
  const from = row.warehouses.find(item => item.quantity > 0)?.warehouse_id ?? list[0]?.id ?? 0
  Object.assign(transferForm, { product_id: row.id, product_name: row.name, from_warehouse_id: from, to_warehouse_id: list.find(item => item.id !== from)?.id ?? 0, quantity: 1, note: '' })
  transferErrors.value = {}
  transferModal.value = true
}

async function saveTransfer(): Promise<void> {
  savingTransfer.value = true
  transferErrors.value = {}

  try {
    await api(`${apiBase.value}/inventory/transfers`, {
      method: 'POST',
      body: { product_id: transferForm.product_id, from_warehouse_id: transferForm.from_warehouse_id, to_warehouse_id: transferForm.to_warehouse_id, quantity: transferForm.quantity, note: transferForm.note || null },
    })
    transferModal.value = false
    toast.success('Transfert enregistré.')
    await refreshStock()
  }
  catch (requestError) {
    transferErrors.value = validationErrors(requestError)

    if (!Object.keys(transferErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingTransfer.value = false
  }
}

/* Warehouses CRUD */
const warehouseModal = ref(false)
const warehouseForm = reactive({ id: null as number | null, name: '', code: '', address: '', is_default: false })
const warehouseErrors = ref<Record<string, string>>({})
const savingWarehouse = ref(false)
const busyWarehouse = ref<number | null>(null)

function openWarehouse(warehouse?: Warehouse): void {
  Object.assign(warehouseForm, warehouse
    ? { id: warehouse.id, name: warehouse.name, code: warehouse.code, address: warehouse.address ?? '', is_default: warehouse.is_default }
    : { id: null, name: '', code: '', address: '', is_default: false })
  warehouseErrors.value = {}
  warehouseModal.value = true
}

async function saveWarehouse(): Promise<void> {
  savingWarehouse.value = true
  warehouseErrors.value = {}
  const body = { name: warehouseForm.name, code: warehouseForm.code.trim().toUpperCase(), address: warehouseForm.address || null, is_default: warehouseForm.is_default }

  try {
    if (warehouseForm.id) {
      await api(`${apiBase.value}/warehouses/${warehouseForm.id}`, { method: 'PUT', body })
    }
    else {
      await api(`${apiBase.value}/warehouses`, { method: 'POST', body })
    }

    warehouseModal.value = false
    toast.success('Entrepôt enregistré.')
    await refreshWarehouses()
  }
  catch (requestError) {
    warehouseErrors.value = validationErrors(requestError)

    if (!Object.keys(warehouseErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingWarehouse.value = false
  }
}

async function deleteWarehouse(warehouse: Warehouse): Promise<void> {
  if (!window.confirm(`Supprimer l’entrepôt « ${warehouse.name} » ?`)) {
    return
  }

  busyWarehouse.value = warehouse.id

  try {
    await api(`${apiBase.value}/warehouses/${warehouse.id}`, { method: 'DELETE' })
    toast.success('Entrepôt supprimé.')
    await refreshWarehouses()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'Cet entrepôt ne peut pas être supprimé.'))
  }
  finally {
    busyWarehouse.value = null
  }
}

function quantityIn(row: StockRow, warehouseId: number): number {
  return row.warehouses.find(item => item.warehouse_id === warehouseId)?.quantity ?? 0
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="inventory" title="Inventaire" subtitle="Stock par entrepôt, mouvements tracés et réassort intelligent." icon="bi-boxes" @retry="refresh()">
    <ul class="nav nav-tabs dashboard-tabs flex-nowrap overflow-auto mb-4" role="tablist" aria-label="Inventaire">
      <li v-for="tab in TABS" :key="tab.key" class="nav-item" role="presentation">
        <button :id="`inv-tab-${tab.key}`" type="button" class="nav-link text-nowrap" :class="{ active: activeTab === tab.key }" role="tab" :aria-selected="activeTab === tab.key" aria-controls="inv-panel" @click="activeTab = tab.key">
          <i class="bi me-1" :class="tab.icon" aria-hidden="true" />{{ tab.label }}
        </button>
      </li>
    </ul>

    <div id="inv-panel" role="tabpanel" :aria-labelledby="`inv-tab-${activeTab}`">
      <!-- STOCK -->
      <div v-if="activeTab === 'stock'" class="card">
        <div class="card-body d-flex flex-column flex-md-row gap-2">
          <label for="stock-search" class="visually-hidden">Rechercher un produit</label>
          <input id="stock-search" v-model="stockSearch" type="search" class="form-control" placeholder="Rechercher un produit ou un SKU…">
          <label for="stock-warehouse" class="visually-hidden">Filtrer par entrepôt</label>
          <select id="stock-warehouse" v-model="stockWarehouse" class="form-select" style="max-width: 16rem;">
            <option value="">Tous les entrepôts</option>
            <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
          </select>
        </div>
        <AppState v-if="stockStatus === 'pending' && !stock" loading text="Chargement du stock…" />
        <AppState v-else-if="stockError" variant="error" title="Stock indisponible" :text="errorMessage(stockError)">
          <button type="button" class="btn btn-primary" @click="refreshStock()">Réessayer</button>
        </AppState>
        <AppState v-else-if="!stock?.data.length" icon="bi-box-seam" title="Aucun produit" text="Ajoutez des produits à votre boutique pour suivre leur stock ici." />
        <div v-else class="table-responsive">
          <table class="table table-stack align-middle mb-0">
            <caption class="visually-hidden">Stock par produit et par entrepôt</caption>
            <thead class="table-light">
              <tr>
                <th scope="col">Produit</th>
                <th scope="col" class="text-end">Total</th>
                <th v-for="warehouse in warehouses ?? []" :key="warehouse.id" scope="col" class="text-end">{{ warehouse.code }}</th>
                <th scope="col" class="text-end">Seuil</th>
                <th scope="col" class="text-end">Valeur</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in stock.data" :key="row.id">
                <td>
                  <span class="fw-semibold">{{ row.name }}</span>
                  <span v-if="row.sku" class="d-block small text-muted">{{ row.sku }}</span>
                </td>
                <td class="text-end" data-label="Total">
                  <span class="badge rounded-pill" :class="row.stock_quantity <= 0 ? 'text-bg-danger' : row.reorder_point !== null && row.stock_quantity <= row.reorder_point ? 'text-bg-warning' : 'text-bg-light border'">{{ formatNumber(row.stock_quantity) }}</span>
                </td>
                <td v-for="warehouse in warehouses ?? []" :key="warehouse.id" class="text-end" :data-label="warehouse.name">{{ formatNumber(quantityIn(row, warehouse.id)) }}</td>
                <td class="text-end" data-label="Seuil">{{ row.reorder_point ?? 'auto' }}</td>
                <td class="text-end text-nowrap" data-label="Valeur">{{ formatPrice(row.stock_value, currency) }}</td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-light" :aria-label="`Inventorier ${row.name}`" title="Inventaire physique" @click="openAdjust(row)"><i class="bi bi-clipboard-check" aria-hidden="true" /></button>
                  <button v-if="(warehouses?.length ?? 0) > 1" type="button" class="btn btn-sm btn-light ms-1" :aria-label="`Transférer ${row.name}`" title="Transfert entre entrepôts" @click="openTransfer(row)"><i class="bi bi-arrow-left-right" aria-hidden="true" /></button>
                  <button type="button" class="btn btn-sm btn-light ms-1" :aria-label="`Seuil et coût de ${row.name}`" title="Seuil de réassort et coût" @click="editProduct(row)"><i class="bi bi-sliders" aria-hidden="true" /></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-3 pb-2"><AppPagination v-model:page="stockPage" :meta="stock?.meta" /></div>
      </div>

      <!-- MOVEMENTS -->
      <div v-else-if="activeTab === 'mouvements'" class="card">
        <div class="card-body d-flex flex-column flex-md-row gap-2">
          <label for="movement-type" class="visually-hidden">Type de mouvement</label>
          <select id="movement-type" v-model="movementType" class="form-select">
            <option value="">Tous les types</option>
            <option v-for="type in MOVEMENT_TYPES" :key="type.value" :value="type.value">{{ type.label }}</option>
          </select>
          <label for="movement-warehouse" class="visually-hidden">Entrepôt</label>
          <select id="movement-warehouse" v-model="movementWarehouse" class="form-select">
            <option value="">Tous les entrepôts</option>
            <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
          </select>
        </div>
        <AppState v-if="movementStatus === 'pending' && !movements" loading text="Chargement des mouvements…" />
        <AppState v-else-if="movementError" variant="error" title="Mouvements indisponibles" :text="errorMessage(movementError)">
          <button type="button" class="btn btn-primary" @click="refreshMovements()">Réessayer</button>
        </AppState>
        <AppState v-else-if="!movements?.data.length" icon="bi-arrow-left-right" title="Aucun mouvement" text="Chaque vente, réception, inventaire ou transfert apparaîtra ici." />
        <div v-else class="table-responsive">
          <table class="table table-stack align-middle mb-0">
            <caption class="visually-hidden">Journal des mouvements de stock</caption>
            <thead class="table-light">
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Produit</th>
                <th scope="col">Type</th>
                <th scope="col">Entrepôt</th>
                <th scope="col" class="text-end">Quantité</th>
                <th scope="col" class="text-end">Solde</th>
                <th scope="col">Référence</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="movement in movements.data" :key="movement.id">
                <td class="small text-nowrap" data-label="Date">{{ formatDate(movement.created_at) }}</td>
                <td class="fw-semibold" data-label="Produit">{{ movement.product.name }}</td>
                <td data-label="Type">{{ movement.type_label }}</td>
                <td data-label="Entrepôt">{{ movement.warehouse.code }}</td>
                <td class="text-end fw-semibold" :class="movement.quantity >= 0 ? 'text-success' : 'text-danger'" data-label="Quantité">{{ movement.quantity > 0 ? '+' : '' }}{{ formatNumber(movement.quantity) }}</td>
                <td class="text-end" data-label="Solde">{{ formatNumber(movement.warehouse_quantity_after) }}</td>
                <td class="small" data-label="Référence">
                  {{ movement.reference ?? '—' }}
                  <span v-if="movement.note" class="d-block text-muted">{{ movement.note }}</span>
                  <span v-if="movement.user" class="d-block text-muted">par {{ movement.user }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-3 pb-2"><AppPagination v-model:page="movementPage" :meta="movements?.meta" /></div>
      </div>

      <!-- SUGGESTIONS -->
      <div v-else-if="activeTab === 'reassort'" class="card">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
          <div>
            <h2 class="h5 fw-bold mb-1"><i class="bi bi-lightbulb text-warning me-1" aria-hidden="true" />Réapprovisionnement intelligent</h2>
            <p class="small text-muted mb-0">Calculé à partir de vos ventes récentes, du délai fournisseur, du seuil de réassort et des commandes déjà en route.</p>
          </div>
          <button v-if="hasModule('purchase') && suggestions?.length" type="button" class="btn btn-primary" :disabled="creatingOrders" @click="createPurchaseOrders">
            <span v-if="creatingOrders" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
            <i v-else class="bi bi-cart-plus me-1" aria-hidden="true" />Créer les bons de commande{{ selectedSuggestions.length ? ` (${selectedSuggestions.length})` : '' }}
          </button>
          <NuxtLink v-else-if="!hasModule('purchase')" :to="`${basePath}/applications`" class="btn btn-outline-primary btn-sm">Installer Achats pour commander en un clic</NuxtLink>
        </div>
        <AppState v-if="suggestionStatus === 'pending' && !suggestions" loading text="Analyse des ventes…" />
        <AppState v-else-if="suggestionError" variant="error" title="Analyse indisponible" :text="errorMessage(suggestionError)">
          <button type="button" class="btn btn-primary" @click="refreshSuggestions()">Réessayer</button>
        </AppState>
        <AppState v-else-if="!suggestions?.length" icon="bi-check2-circle" title="Rien à commander" text="Votre stock couvre les ventes prévues. Fixez un seuil de réassort sur vos produits clés pour être alerté plus tôt." />
        <div v-else class="table-responsive">
          <table class="table table-stack align-middle mb-0">
            <caption class="visually-hidden">Produits à réapprovisionner</caption>
            <thead class="table-light">
              <tr>
                <th v-if="hasModule('purchase')" scope="col"><span class="visually-hidden">Sélection</span></th>
                <th scope="col">Produit</th>
                <th scope="col" class="text-end">Stock</th>
                <th scope="col" class="text-end">En route</th>
                <th scope="col" class="text-end">Ventes / jour</th>
                <th scope="col">Rupture prévue</th>
                <th scope="col" class="text-end">À commander</th>
                <th scope="col">Fournisseur</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in suggestions" :key="item.product.id">
                <td v-if="hasModule('purchase')">
                  <input :id="`suggest-${item.product.id}`" v-model="selectedSuggestions" type="checkbox" class="form-check-input" :value="item.product.id" :aria-label="`Sélectionner ${item.product.name}`">
                </td>
                <td>
                  <span class="badge me-1" :class="item.urgency === 'critical' ? 'text-bg-danger' : 'text-bg-warning'">{{ item.urgency === 'critical' ? 'Urgent' : 'À prévoir' }}</span>
                  <span class="fw-semibold">{{ item.product.name }}</span>
                </td>
                <td class="text-end" data-label="Stock">{{ formatNumber(item.stock) }} <span class="small text-muted">/ seuil {{ item.reorder_point }}</span></td>
                <td class="text-end" data-label="En route">{{ formatNumber(item.incoming) }}</td>
                <td class="text-end" data-label="Ventes / jour">{{ formatNumber(item.sales_velocity, 2) }}</td>
                <td class="small" data-label="Rupture prévue">
                  <template v-if="item.stockout_date">{{ formatShortDate(item.stockout_date) }} <span class="text-muted">({{ formatNumber(item.days_of_cover, 1) }} j)</span></template>
                  <template v-else>—</template>
                </td>
                <td class="text-end fw-bold" data-label="À commander">
                  {{ formatNumber(item.suggested_quantity) }}
                  <span v-if="item.estimated_cost" class="d-block small text-muted fw-normal">≈ {{ formatPrice(item.estimated_cost, currency) }}</span>
                </td>
                <td class="small" data-label="Fournisseur">{{ item.supplier ? `${item.supplier.name} (${item.supplier.lead_time_days} j)` : 'Non défini' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- WAREHOUSES -->
      <div v-else class="card">
        <div class="card-body d-flex justify-content-between align-items-center">
          <h2 class="h5 fw-bold mb-0">Entrepôts</h2>
          <button type="button" class="btn btn-primary btn-sm" @click="openWarehouse()"><i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouvel entrepôt</button>
        </div>
        <AppState v-if="!warehouses" loading text="Chargement…" />
        <ul v-else class="list-group list-group-flush">
          <li v-for="warehouse in warehouses" :key="warehouse.id" class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
              <span class="fw-semibold">{{ warehouse.name }}</span> <code>{{ warehouse.code }}</code>
              <span v-if="warehouse.is_default" class="badge text-bg-primary ms-1">Par défaut</span>
              <span class="d-block small text-muted">{{ warehouse.address ?? 'Adresse non renseignée' }}<template v-if="warehouse.stock_quantity !== undefined"> · {{ formatNumber(warehouse.stock_quantity) }} article(s)</template></span>
            </div>
            <div class="text-nowrap">
              <button type="button" class="btn btn-sm btn-light" :aria-label="`Modifier ${warehouse.name}`" @click="openWarehouse(warehouse)"><i class="bi bi-pencil" aria-hidden="true" /></button>
              <button v-if="!warehouse.is_default" type="button" class="btn btn-sm btn-light text-danger ms-1" :disabled="busyWarehouse === warehouse.id" :aria-label="`Supprimer ${warehouse.name}`" @click="deleteWarehouse(warehouse)"><i class="bi bi-trash" aria-hidden="true" /></button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <ErpModal v-model:open="productModal" :title="`Paramètres de stock — ${productForm.name}`">
      <form id="product-stock-form" novalidate @submit.prevent="saveProduct">
        <div class="mb-3">
          <label for="reorder-point" class="form-label">Seuil de réassort</label>
          <input id="reorder-point" v-model.number="productForm.reorder_point" type="number" min="0" step="1" class="form-control" :class="{ 'is-invalid': productErrors.reorder_point }" placeholder="Automatique" aria-describedby="reorder-help reorder-error">
          <div id="reorder-help" class="form-text">Laissez vide : le seuil est calculé selon vos ventes et le délai fournisseur.</div>
          <FieldError id="reorder-error" :message="productErrors.reorder_point" />
        </div>
        <div>
          <label for="cost-price" class="form-label">Coût d’achat unitaire</label>
          <input id="cost-price" v-model.number="productForm.cost_price" type="number" min="0" step="any" class="form-control" :class="{ 'is-invalid': productErrors.cost_price }" aria-describedby="cost-help cost-error">
          <div id="cost-help" class="form-text">Mis à jour automatiquement (coût moyen pondéré) à chaque réception fournisseur.</div>
          <FieldError id="cost-error" :message="productErrors.cost_price" />
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="productModal = false">Annuler</button>
        <button type="submit" form="product-stock-form" class="btn btn-primary" :disabled="savingProduct">Enregistrer</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="adjustModal" :title="`Inventaire physique — ${adjustForm.product_name}`">
      <form id="adjust-form" novalidate @submit.prevent="saveAdjust">
        <div class="mb-3">
          <label for="adjust-warehouse" class="form-label">Entrepôt</label>
          <select id="adjust-warehouse" v-model.number="adjustForm.warehouse_id" class="form-select" :class="{ 'is-invalid': adjustErrors.warehouse_id }">
            <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
          </select>
          <FieldError id="adjust-warehouse-error" :message="adjustErrors.warehouse_id" />
        </div>
        <div class="mb-3">
          <label for="adjust-quantity" class="form-label">Quantité comptée</label>
          <input id="adjust-quantity" v-model.number="adjustForm.counted_quantity" type="number" min="0" step="1" class="form-control" :class="{ 'is-invalid': adjustErrors.counted_quantity || adjustErrors.quantity }" aria-describedby="adjust-help adjust-quantity-error">
          <div id="adjust-help" class="form-text">Stock théorique : {{ formatNumber(adjustCurrent) }} · écart : {{ (adjustForm.counted_quantity || 0) - adjustCurrent > 0 ? '+' : '' }}{{ (adjustForm.counted_quantity || 0) - adjustCurrent }}</div>
          <FieldError id="adjust-quantity-error" :message="adjustErrors.counted_quantity ?? adjustErrors.quantity" />
        </div>
        <div>
          <label for="adjust-note" class="form-label">Motif</label>
          <input id="adjust-note" v-model="adjustForm.note" type="text" maxlength="255" class="form-control" placeholder="Ex. : casse, vol, erreur de saisie…">
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="adjustModal = false">Annuler</button>
        <button type="submit" form="adjust-form" class="btn btn-primary" :disabled="savingAdjust">Valider l’inventaire</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="transferModal" :title="`Transfert — ${transferForm.product_name}`">
      <form id="transfer-form" novalidate @submit.prevent="saveTransfer">
        <div class="row g-3">
          <div class="col-sm-6">
            <label for="transfer-from" class="form-label">Depuis</label>
            <select id="transfer-from" v-model.number="transferForm.from_warehouse_id" class="form-select" :class="{ 'is-invalid': transferErrors.from_warehouse_id }">
              <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <FieldError id="transfer-from-error" :message="transferErrors.from_warehouse_id" />
          </div>
          <div class="col-sm-6">
            <label for="transfer-to" class="form-label">Vers</label>
            <select id="transfer-to" v-model.number="transferForm.to_warehouse_id" class="form-select" :class="{ 'is-invalid': transferErrors.to_warehouse_id }">
              <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <FieldError id="transfer-to-error" :message="transferErrors.to_warehouse_id" />
          </div>
          <div class="col-sm-6">
            <label for="transfer-quantity" class="form-label">Quantité</label>
            <input id="transfer-quantity" v-model.number="transferForm.quantity" type="number" min="1" step="1" class="form-control" :class="{ 'is-invalid': transferErrors.quantity }">
            <FieldError id="transfer-quantity-error" :message="transferErrors.quantity" />
          </div>
          <div class="col-sm-6">
            <label for="transfer-note" class="form-label">Note</label>
            <input id="transfer-note" v-model="transferForm.note" type="text" maxlength="255" class="form-control">
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="transferModal = false">Annuler</button>
        <button type="submit" form="transfer-form" class="btn btn-primary" :disabled="savingTransfer">Transférer</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="warehouseModal" :title="warehouseForm.id ? 'Modifier l’entrepôt' : 'Nouvel entrepôt'">
      <form id="warehouse-form" novalidate @submit.prevent="saveWarehouse">
        <div class="row g-3">
          <div class="col-sm-8">
            <label for="warehouse-name" class="form-label">Nom <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="warehouse-name" v-model="warehouseForm.name" type="text" maxlength="120" required class="form-control" :class="{ 'is-invalid': warehouseErrors.name }" placeholder="Ex. : Magasin Plateau">
            <FieldError id="warehouse-name-error" :message="warehouseErrors.name" />
          </div>
          <div class="col-sm-4">
            <label for="warehouse-code" class="form-label">Code <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="warehouse-code" v-model="warehouseForm.code" type="text" maxlength="12" required class="form-control text-uppercase" :class="{ 'is-invalid': warehouseErrors.code }" placeholder="PLT">
            <FieldError id="warehouse-code-error" :message="warehouseErrors.code" />
          </div>
          <div class="col-12">
            <label for="warehouse-address" class="form-label">Adresse</label>
            <input id="warehouse-address" v-model="warehouseForm.address" type="text" maxlength="255" class="form-control">
          </div>
          <div class="col-12 form-check form-switch ms-2">
            <input id="warehouse-default" v-model="warehouseForm.is_default" class="form-check-input" type="checkbox" role="switch">
            <label for="warehouse-default" class="form-check-label">Entrepôt par défaut (ventes en ligne et réceptions)</label>
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="warehouseModal = false">Annuler</button>
        <button type="submit" form="warehouse-form" class="btn btn-primary" :disabled="savingWarehouse">Enregistrer</button>
      </template>
    </ErpModal>
  </ErpPage>
</template>

<script setup lang="ts">
import type { Activity, ErpPaginated, PurchaseOrder, PurchaseOrderStatus, Resource, Supplier, Warehouse } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const TABS = [
  { key: 'commandes', label: 'Bons de commande', icon: 'bi-file-earmark-text' },
  { key: 'fournisseurs', label: 'Fournisseurs', icon: 'bi-person-vcard' },
] as const
type TabKey = typeof TABS[number]['key']

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { shop, loading, error, refresh, isInstalled, apiBase } = useErpShop(() => String(route.params.id), 'purchase')
const { products, load: loadProducts } = useShopProducts(() => String(route.params.id))

useSeoMeta({ title: 'Achats' })

const activeTab = computed<TabKey>({
  get: () => TABS.find(tab => tab.key === route.query.onglet)?.key ?? 'commandes',
  set: value => void router.replace({ query: { ...route.query, onglet: value } }),
})
const currency = computed(() => shop.value?.currency ?? 'XOF')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)

/* Suppliers */
const { data: suppliers, status: supplierStatus, error: supplierError, refresh: refreshSuppliers } = useAsyncData(
  () => `erp-suppliers-${route.params.id}`,
  () => (enabled.value ? api<Resource<Supplier[]>>(`${apiBase.value}/suppliers`).then(response => response.data) : Promise.resolve(null)),
  { server: false, watch: [enabled] },
)

const { data: warehouses } = useAsyncData(
  () => `erp-po-warehouses-${route.params.id}`,
  () => (enabled.value ? api<Resource<Warehouse[]>>(`${apiBase.value}/warehouses`).then(response => response.data) : Promise.resolve([] as Warehouse[])),
  { server: false, watch: [enabled] },
)

const supplierModal = ref(false)
const supplierForm = reactive({ id: null as number | null, name: '', contact_name: '', email: '', phone: '', address: '', payment_terms_days: 0, lead_time_days: 7, notes: '' })
const supplierErrors = ref<Record<string, string>>({})
const savingSupplier = ref(false)

function openSupplier(supplier?: Supplier): void {
  Object.assign(supplierForm, supplier
    ? { id: supplier.id, name: supplier.name, contact_name: supplier.contact_name ?? '', email: supplier.email ?? '', phone: supplier.phone ?? '', address: supplier.address ?? '', payment_terms_days: supplier.payment_terms_days, lead_time_days: supplier.lead_time_days, notes: supplier.notes ?? '' }
    : { id: null, name: '', contact_name: '', email: '', phone: '', address: '', payment_terms_days: 0, lead_time_days: 7, notes: '' })
  supplierErrors.value = {}
  supplierModal.value = true
}

async function saveSupplier(): Promise<void> {
  savingSupplier.value = true
  supplierErrors.value = {}
  const { id, ...fields } = supplierForm
  const body = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]))

  try {
    if (id) {
      await api(`${apiBase.value}/suppliers/${id}`, { method: 'PUT', body })
    }
    else {
      await api(`${apiBase.value}/suppliers`, { method: 'POST', body })
    }

    supplierModal.value = false
    toast.success('Fournisseur enregistré.')
    await refreshSuppliers()
  }
  catch (requestError) {
    supplierErrors.value = validationErrors(requestError)

    if (!Object.keys(supplierErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingSupplier.value = false
  }
}

async function deleteSupplier(supplier: Supplier): Promise<void> {
  if (!window.confirm(`Supprimer le fournisseur « ${supplier.name} » ?`)) {
    return
  }

  try {
    await api(`${apiBase.value}/suppliers/${supplier.id}`, { method: 'DELETE' })
    toast.success('Fournisseur supprimé.')
    await refreshSuppliers()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'Ce fournisseur ne peut pas être supprimé.'))
  }
}

/* Purchase orders */
const orderPage = ref(1)
const orderStatusFilter = ref<PurchaseOrderStatus | ''>('')
watch(orderStatusFilter, () => (orderPage.value = 1))

const { data: orders, status: orderStatus, error: orderError, refresh: refreshOrders } = useAsyncData(
  () => `erp-purchase-orders-${route.params.id}`,
  () => (enabled.value
    ? api<ErpPaginated<PurchaseOrder>>(`${apiBase.value}/purchase-orders${queryString({ page: orderPage.value, status: orderStatusFilter.value })}`)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, orderPage, orderStatusFilter] },
)

interface LineDraft { product_id: number | '', quantity: number, unit_cost: number }

const orderModal = ref(false)
const orderForm = reactive({ id: null as number | null, supplier_id: '' as number | '', warehouse_id: '' as number | '', expected_at: '', notes: '', lines: [] as LineDraft[] })
const orderErrors = ref<Record<string, string>>({})
const savingOrder = ref(false)
const orderTotal = computed(() => orderForm.lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0), 0))

async function openOrderForm(order?: PurchaseOrder): Promise<void> {
  orderErrors.value = {}
  loadProducts().catch(() => toast.error('Impossible de charger vos produits.'))

  if (order) {
    const { data } = await api<Resource<PurchaseOrder>>(`${apiBase.value}/purchase-orders/${order.id}`)
    Object.assign(orderForm, {
      id: data.id,
      supplier_id: data.supplier.id,
      warehouse_id: data.warehouse.id,
      expected_at: data.expected_at ?? '',
      notes: data.notes ?? '',
      lines: (data.lines ?? []).map(line => ({ product_id: line.product.id, quantity: line.quantity, unit_cost: line.unit_cost })),
    })
  }
  else {
    Object.assign(orderForm, { id: null, supplier_id: suppliers.value?.[0]?.id ?? '', warehouse_id: warehouses.value?.find(item => item.is_default)?.id ?? '', expected_at: '', notes: '', lines: [{ product_id: '', quantity: 1, unit_cost: 0 }] })
  }

  detailModal.value = false
  orderModal.value = true
}

function onProductPicked(line: LineDraft): void {
  const product = products.value?.find(item => item.id === line.product_id) as (typeof products.value extends (infer T)[] | null ? T : never) & { cost_price?: number | null } | undefined

  if (product && !line.unit_cost && product.cost_price) {
    line.unit_cost = Number(product.cost_price)
  }
}

async function saveOrder(): Promise<void> {
  savingOrder.value = true
  orderErrors.value = {}
  const body = {
    supplier_id: orderForm.supplier_id || null,
    warehouse_id: orderForm.warehouse_id || null,
    expected_at: orderForm.expected_at || null,
    notes: orderForm.notes || null,
    lines: orderForm.lines.filter(line => line.product_id !== ''),
  }

  try {
    const { data } = orderForm.id
      ? await api<Resource<PurchaseOrder>>(`${apiBase.value}/purchase-orders/${orderForm.id}`, { method: 'PUT', body })
      : await api<Resource<PurchaseOrder>>(`${apiBase.value}/purchase-orders`, { method: 'POST', body })
    orderModal.value = false
    toast.success(`Bon de commande ${data.number} enregistré.`)
    await refreshOrders()
    await openDetail(data)
  }
  catch (requestError) {
    orderErrors.value = validationErrors(requestError)

    if (!Object.keys(orderErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    savingOrder.value = false
  }
}

/* Detail / workflow */
const detailModal = ref(false)
const detail = ref<PurchaseOrder | null>(null)
const history = ref<Activity[]>([])
const receiveQuantities = ref<Record<number, number>>({})
const working = ref(false)

async function openDetail(order: PurchaseOrder): Promise<void> {
  try {
    const [{ data }, activities] = await Promise.all([
      api<Resource<PurchaseOrder>>(`${apiBase.value}/purchase-orders/${order.id}`),
      api<Resource<Activity[]>>(`${apiBase.value}/activities?subject_type=purchase_order&subject_id=${order.id}`).catch(() => ({ data: [] as Activity[] })),
    ])
    setDetail(data)
    history.value = activities.data
    detailModal.value = true
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
}

function setDetail(order: PurchaseOrder): void {
  detail.value = order
  receiveQuantities.value = Object.fromEntries((order.lines ?? []).map(line => [line.id, line.remaining_quantity]))
}

async function runAction(action: 'confirm' | 'receive' | 'cancel'): Promise<void> {
  if (!detail.value) {
    return
  }

  if (action === 'cancel' && !window.confirm(`Annuler le bon de commande ${detail.value.number} ?`)) {
    return
  }

  working.value = true

  try {
    const body = action === 'receive'
      ? { lines: Object.entries(receiveQuantities.value).map(([id, quantity]) => ({ id: Number(id), quantity: Number(quantity) || 0 })).filter(line => line.quantity > 0) }
      : undefined
    const { data } = await api<Resource<PurchaseOrder>>(`${apiBase.value}/purchase-orders/${detail.value.id}/${action}`, { method: 'POST', body })
    setDetail(data)
    history.value = (await api<Resource<Activity[]>>(`${apiBase.value}/activities?subject_type=purchase_order&subject_id=${data.id}`).catch(() => ({ data: history.value }))).data
    toast.success({ confirm: 'Bon de commande confirmé.', receive: 'Réception enregistrée : le stock est à jour.', cancel: 'Bon de commande annulé.' }[action])
    await refreshOrders()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    working.value = false
  }
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="purchase" title="Achats" subtitle="Fournisseurs, bons de commande et réceptions de marchandise." icon="bi-truck" @retry="refresh()">
    <template #actions>
      <button v-if="activeTab === 'commandes'" type="button" class="btn btn-primary" :disabled="!suppliers?.length" :title="!suppliers?.length ? 'Ajoutez d’abord un fournisseur' : undefined" @click="openOrderForm()">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouveau bon de commande
      </button>
      <button v-else type="button" class="btn btn-primary" @click="openSupplier()">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouveau fournisseur
      </button>
    </template>

    <ul class="nav nav-tabs dashboard-tabs mb-4" role="tablist" aria-label="Achats">
      <li v-for="tab in TABS" :key="tab.key" class="nav-item" role="presentation">
        <button :id="`po-tab-${tab.key}`" type="button" class="nav-link" :class="{ active: activeTab === tab.key }" role="tab" :aria-selected="activeTab === tab.key" aria-controls="po-panel" @click="activeTab = tab.key">
          <i class="bi me-1" :class="tab.icon" aria-hidden="true" />{{ tab.label }}
        </button>
      </li>
    </ul>

    <div id="po-panel" role="tabpanel" :aria-labelledby="`po-tab-${activeTab}`">
      <div v-if="activeTab === 'commandes'" class="card">
        <div class="card-body">
          <label for="po-status" class="visually-hidden">Filtrer par statut</label>
          <select id="po-status" v-model="orderStatusFilter" class="form-select" style="max-width: 16rem;">
            <option value="">Tous les statuts</option>
            <option v-for="item in PURCHASE_STATUSES" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </div>
        <AppState v-if="orderStatus === 'pending' && !orders" loading text="Chargement des bons de commande…" />
        <AppState v-else-if="orderError" variant="error" title="Bons de commande indisponibles" :text="errorMessage(orderError)">
          <button type="button" class="btn btn-primary" @click="refreshOrders()">Réessayer</button>
        </AppState>
        <AppState v-else-if="!orders?.data.length" icon="bi-file-earmark-text" title="Aucun bon de commande" :text="suppliers?.length ? 'Commandez auprès de vos fournisseurs : le stock sera mis à jour à la réception.' : 'Commencez par ajouter un fournisseur.'" />
        <div v-else class="table-responsive">
          <table class="table table-stack table-hover align-middle mb-0">
            <caption class="visually-hidden">Bons de commande fournisseurs</caption>
            <thead class="table-light">
              <tr>
                <th scope="col">Numéro</th>
                <th scope="col">Fournisseur</th>
                <th scope="col">Statut</th>
                <th scope="col">Livraison prévue</th>
                <th scope="col" class="text-end">Total</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders.data" :key="order.id">
                <td class="fw-semibold">{{ order.number }}</td>
                <td data-label="Fournisseur">{{ order.supplier.name }}</td>
                <td data-label="Statut">
                  <ErpStatusBadge :label="order.status_label" :variant="PURCHASE_STATUS_VARIANTS[order.status]" />
                  <span v-if="order.is_late" class="badge text-bg-danger ms-1">En retard</span>
                </td>
                <td data-label="Livraison prévue">{{ formatShortDate(order.expected_at) }}</td>
                <td class="text-end text-nowrap" data-label="Total">{{ formatPrice(order.total, order.currency) }}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-light" :aria-label="`Ouvrir ${order.number}`" @click="openDetail(order)"><i class="bi bi-eye" aria-hidden="true" /></button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-3 pb-2"><AppPagination v-model:page="orderPage" :meta="orders?.meta" /></div>
      </div>

      <div v-else class="card">
        <AppState v-if="supplierStatus === 'pending' && !suppliers" loading text="Chargement des fournisseurs…" />
        <AppState v-else-if="supplierError" variant="error" title="Fournisseurs indisponibles" :text="errorMessage(supplierError)">
          <button type="button" class="btn btn-primary" @click="refreshSuppliers()">Réessayer</button>
        </AppState>
        <AppState v-else-if="!suppliers?.length" icon="bi-person-vcard" title="Aucun fournisseur" text="Enregistrez vos grossistes et fabricants avec leur délai de livraison.">
          <button type="button" class="btn btn-primary" @click="openSupplier()">Ajouter un fournisseur</button>
        </AppState>
        <div v-else class="table-responsive">
          <table class="table table-stack align-middle mb-0">
            <caption class="visually-hidden">Fournisseurs</caption>
            <thead class="table-light">
              <tr>
                <th scope="col">Fournisseur</th>
                <th scope="col">Contact</th>
                <th scope="col">Délai</th>
                <th scope="col">Paiement</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="supplier in suppliers" :key="supplier.id">
                <td>
                  <span class="fw-semibold">{{ supplier.name }}</span>
                  <span v-if="supplier.purchase_orders_count !== undefined" class="d-block small text-muted">{{ supplier.purchase_orders_count }} bon(s) de commande</span>
                </td>
                <td class="small" data-label="Contact">
                  {{ supplier.contact_name ?? '—' }}
                  <a v-if="supplier.phone" :href="`tel:${supplier.phone}`" class="d-block">{{ supplier.phone }}</a>
                  <a v-if="supplier.email" :href="`mailto:${supplier.email}`" class="d-block">{{ supplier.email }}</a>
                </td>
                <td data-label="Délai">{{ supplier.lead_time_days }} j</td>
                <td data-label="Paiement">{{ supplier.payment_terms_days ? `${supplier.payment_terms_days} j` : 'Comptant' }}</td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-light" :aria-label="`Modifier ${supplier.name}`" @click="openSupplier(supplier)"><i class="bi bi-pencil" aria-hidden="true" /></button>
                  <button type="button" class="btn btn-sm btn-light text-danger ms-1" :aria-label="`Supprimer ${supplier.name}`" @click="deleteSupplier(supplier)"><i class="bi bi-trash" aria-hidden="true" /></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <ErpModal v-model:open="supplierModal" :title="supplierForm.id ? 'Modifier le fournisseur' : 'Nouveau fournisseur'" size="lg">
      <form id="supplier-form" novalidate @submit.prevent="saveSupplier">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="supplier-name" class="form-label">Raison sociale <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="supplier-name" v-model="supplierForm.name" type="text" maxlength="160" required class="form-control" :class="{ 'is-invalid': supplierErrors.name }">
            <FieldError id="supplier-name-error" :message="supplierErrors.name" />
          </div>
          <div class="col-md-6">
            <label for="supplier-contact" class="form-label">Contact</label>
            <input id="supplier-contact" v-model="supplierForm.contact_name" type="text" maxlength="120" class="form-control">
          </div>
          <div class="col-md-6">
            <label for="supplier-phone" class="form-label">Téléphone / WhatsApp</label>
            <input id="supplier-phone" v-model="supplierForm.phone" type="tel" maxlength="40" class="form-control" :class="{ 'is-invalid': supplierErrors.phone }">
            <FieldError id="supplier-phone-error" :message="supplierErrors.phone" />
          </div>
          <div class="col-md-6">
            <label for="supplier-email" class="form-label">E-mail</label>
            <input id="supplier-email" v-model="supplierForm.email" type="email" class="form-control" :class="{ 'is-invalid': supplierErrors.email }">
            <FieldError id="supplier-email-error" :message="supplierErrors.email" />
          </div>
          <div class="col-12">
            <label for="supplier-address" class="form-label">Adresse</label>
            <input id="supplier-address" v-model="supplierForm.address" type="text" maxlength="255" class="form-control">
          </div>
          <div class="col-sm-6">
            <label for="supplier-lead" class="form-label">Délai de livraison (jours)</label>
            <input id="supplier-lead" v-model.number="supplierForm.lead_time_days" type="number" min="0" class="form-control" :class="{ 'is-invalid': supplierErrors.lead_time_days }">
            <FieldError id="supplier-lead-error" :message="supplierErrors.lead_time_days" />
          </div>
          <div class="col-sm-6">
            <label for="supplier-terms" class="form-label">Délai de paiement (jours)</label>
            <input id="supplier-terms" v-model.number="supplierForm.payment_terms_days" type="number" min="0" class="form-control" :class="{ 'is-invalid': supplierErrors.payment_terms_days }">
            <FieldError id="supplier-terms-error" :message="supplierErrors.payment_terms_days" />
          </div>
          <div class="col-12">
            <label for="supplier-notes" class="form-label">Notes</label>
            <textarea id="supplier-notes" v-model="supplierForm.notes" rows="2" class="form-control" />
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="supplierModal = false">Annuler</button>
        <button type="submit" form="supplier-form" class="btn btn-primary" :disabled="savingSupplier">Enregistrer</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="orderModal" :title="orderForm.id ? 'Modifier le bon de commande' : 'Nouveau bon de commande'" size="xl">
      <form id="po-form" novalidate @submit.prevent="saveOrder">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label for="po-supplier" class="form-label">Fournisseur <span class="text-danger" aria-hidden="true">*</span></label>
            <select id="po-supplier" v-model.number="orderForm.supplier_id" class="form-select" :class="{ 'is-invalid': orderErrors.supplier_id }">
              <option v-for="supplier in suppliers ?? []" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <FieldError id="po-supplier-error" :message="orderErrors.supplier_id" />
          </div>
          <div class="col-md-4">
            <label for="po-warehouse" class="form-label">Entrepôt de réception</label>
            <select id="po-warehouse" v-model.number="orderForm.warehouse_id" class="form-select" :class="{ 'is-invalid': orderErrors.warehouse_id }">
              <option value="">Entrepôt par défaut</option>
              <option v-for="warehouse in warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <FieldError id="po-warehouse-error" :message="orderErrors.warehouse_id" />
          </div>
          <div class="col-md-4">
            <label for="po-expected" class="form-label">Livraison prévue</label>
            <input id="po-expected" v-model="orderForm.expected_at" type="date" class="form-control" :class="{ 'is-invalid': orderErrors.expected_at }">
            <FieldError id="po-expected-error" :message="orderErrors.expected_at" />
          </div>
        </div>
        <fieldset>
          <legend class="h6 fw-bold">Articles</legend>
          <FieldError id="po-lines-error" :message="orderErrors.lines" />
          <div v-for="(line, index) in orderForm.lines" :key="index" class="row g-2 align-items-end mb-2">
            <div class="col-12 col-md-6">
              <label :for="`po-line-product-${index}`" class="form-label small">Produit</label>
              <select :id="`po-line-product-${index}`" v-model.number="line.product_id" class="form-select" :class="{ 'is-invalid': orderErrors[`lines.${index}.product_id`] }" @change="onProductPicked(line)">
                <option value="" disabled>{{ products ? 'Choisir un produit' : 'Chargement…' }}</option>
                <option v-for="product in products ?? []" :key="product.id" :value="product.id">{{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}</option>
              </select>
            </div>
            <div class="col-4 col-md-2">
              <label :for="`po-line-qty-${index}`" class="form-label small">Quantité</label>
              <input :id="`po-line-qty-${index}`" v-model.number="line.quantity" type="number" min="1" class="form-control" :class="{ 'is-invalid': orderErrors[`lines.${index}.quantity`] }">
            </div>
            <div class="col-5 col-md-3">
              <label :for="`po-line-cost-${index}`" class="form-label small">Coût unitaire</label>
              <input :id="`po-line-cost-${index}`" v-model.number="line.unit_cost" type="number" min="0" step="any" class="form-control" :class="{ 'is-invalid': orderErrors[`lines.${index}.unit_cost`] }">
            </div>
            <div class="col-3 col-md-1 text-end">
              <button type="button" class="btn btn-light text-danger" :disabled="orderForm.lines.length === 1" :aria-label="`Retirer la ligne ${index + 1}`" @click="orderForm.lines.splice(index, 1)"><i class="bi bi-x-lg" aria-hidden="true" /></button>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" @click="orderForm.lines.push({ product_id: '', quantity: 1, unit_cost: 0 })"><i class="bi bi-plus-lg me-1" aria-hidden="true" />Ajouter une ligne</button>
        </fieldset>
        <div class="mt-3">
          <label for="po-notes" class="form-label">Notes</label>
          <textarea id="po-notes" v-model="orderForm.notes" rows="2" class="form-control" />
        </div>
        <p class="text-end fw-bold fs-5 mt-3 mb-0">Total : {{ formatPrice(orderTotal, currency) }}</p>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="orderModal = false">Annuler</button>
        <button type="submit" form="po-form" class="btn btn-primary" :disabled="savingOrder">Enregistrer le brouillon</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="detailModal" :title="detail ? `Bon de commande ${detail.number}` : 'Bon de commande'" size="xl">
      <template v-if="detail">
        <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
          <div>
            <ErpStatusBadge :label="detail.status_label" :variant="PURCHASE_STATUS_VARIANTS[detail.status]" />
            <span v-if="detail.is_late" class="badge text-bg-danger ms-1">En retard</span>
            <p class="mb-0 mt-2"><strong>{{ detail.supplier.name }}</strong> → {{ detail.warehouse.name }}</p>
            <p class="small text-muted mb-0">Livraison prévue : {{ formatShortDate(detail.expected_at) }}</p>
          </div>
          <p class="fs-4 fw-bold mb-0">{{ formatPrice(detail.total, detail.currency) }}</p>
        </div>
        <div class="table-responsive">
          <table class="table table-stack align-middle">
            <caption class="visually-hidden">Lignes du bon de commande</caption>
            <thead class="table-light">
              <tr>
                <th scope="col">Produit</th>
                <th scope="col" class="text-end">Commandé</th>
                <th scope="col" class="text-end">Reçu</th>
                <th scope="col" class="text-end">Coût unitaire</th>
                <th scope="col" class="text-end">Total</th>
                <th v-if="['confirmed', 'partially_received'].includes(detail.status)" scope="col" class="text-end">À réceptionner</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in detail.lines ?? []" :key="line.id">
                <td class="fw-semibold">{{ line.product.name }}</td>
                <td class="text-end" data-label="Commandé">{{ formatNumber(line.quantity) }}</td>
                <td class="text-end" data-label="Reçu">{{ formatNumber(line.quantity_received) }}</td>
                <td class="text-end text-nowrap" data-label="Coût unitaire">{{ formatPrice(line.unit_cost, detail.currency) }}</td>
                <td class="text-end text-nowrap" data-label="Total">{{ formatPrice(line.total, detail.currency) }}</td>
                <td v-if="['confirmed', 'partially_received'].includes(detail.status)" class="text-end" data-label="À réceptionner">
                  <label :for="`receive-${line.id}`" class="visually-hidden">Quantité reçue pour {{ line.product.name }}</label>
                  <input :id="`receive-${line.id}`" v-model.number="receiveQuantities[line.id]" type="number" min="0" :max="line.remaining_quantity" class="form-control form-control-sm ms-auto" style="max-width: 6rem;" :disabled="line.remaining_quantity === 0">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="detail.notes" class="small text-muted">{{ detail.notes }}</p>
        <h3 class="h6 fw-bold mt-4">Historique</h3>
        <ErpActivityFeed :activities="history" />
      </template>
      <template #footer>
        <template v-if="detail">
          <button v-if="['draft', 'confirmed'].includes(detail.status)" type="button" class="btn btn-outline-danger me-auto" :disabled="working" @click="runAction('cancel')">Annuler le bon</button>
          <button v-if="detail.status === 'draft'" type="button" class="btn btn-light" :disabled="working" @click="openOrderForm(detail)"><i class="bi bi-pencil me-1" aria-hidden="true" />Modifier</button>
          <button v-if="detail.status === 'draft'" type="button" class="btn btn-primary" :disabled="working" @click="runAction('confirm')"><i class="bi bi-check2 me-1" aria-hidden="true" />Confirmer la commande</button>
          <button v-if="['confirmed', 'partially_received'].includes(detail.status)" type="button" class="btn btn-success" :disabled="working" @click="runAction('receive')"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true" />Réceptionner</button>
        </template>
      </template>
    </ErpModal>
  </ErpPage>
</template>

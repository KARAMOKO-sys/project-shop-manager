<script setup lang="ts">
import type { ErpPaginated, Invoice, InvoiceableOrder, InvoiceStatus, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Facturation' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = computed(() => String(route.params.id))
const { shop, loading, error, refresh, isInstalled, apiBase, basePath } = useErpShop(shopId, 'invoicing')
const { products, load: loadProducts } = useShopProducts(shopId)

const currency = computed(() => shop.value?.currency ?? 'XOF')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)

/* List */
const page = ref(1)
const statusFilter = ref<InvoiceStatus | ''>('')
const search = ref('')
const searchQuery = debouncedRef(search)
watch([statusFilter, searchQuery], () => (page.value = 1))

const { data: invoices, status: listStatus, error: listError, refresh: refreshList } = useAsyncData(
  () => `erp-invoices-${shopId.value}`,
  () => (enabled.value
    ? api<ErpPaginated<Invoice>>(`${apiBase.value}/invoices${queryString({ page: page.value, status: statusFilter.value, search: searchQuery.value })}`)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, page, statusFilter, searchQuery] },
)

const totals = computed(() => {
  const rows = invoices.value?.data ?? []

  return {
    due: rows.filter(invoice => ['posted', 'partially_paid'].includes(invoice.status)).reduce((sum, invoice) => sum + invoice.amount_due, 0),
    overdue: rows.filter(invoice => invoice.is_overdue).length,
  }
})

/* Invoice an order */
const orderModal = ref(false)
const invoiceableOrders = ref<InvoiceableOrder[] | null>(null)
const loadingOrders = ref(false)
const invoicingOrderId = ref<number | null>(null)

async function openOrderPicker(): Promise<void> {
  orderModal.value = true
  loadingOrders.value = true

  try {
    invoiceableOrders.value = (await api<Resource<InvoiceableOrder[]>>(`${apiBase.value}/invoiceable-orders`)).data
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    loadingOrders.value = false
  }
}

async function invoiceOrder(order: InvoiceableOrder): Promise<void> {
  invoicingOrderId.value = order.id

  try {
    const { data } = await api<Resource<Invoice>>(`${apiBase.value}/invoices/from-order/${order.id}`, { method: 'POST' })
    toast.success(`Facture brouillon créée pour la commande ${order.order_number}.`)
    await navigateTo(`${basePath.value}/factures/${data.id}`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    invoicingOrderId.value = null
  }
}

/* Manual invoice */
interface LineDraft { product_id: number | '', description: string, quantity: number, unit_price: number, tax_rate: number }

const formModal = ref(false)
const saving = ref(false)
const formErrors = ref<Record<string, string>>({})
const form = reactive({ customer_name: '', customer_email: '', issue_date: todayIso(), due_date: todayIso(), notes: '', lines: [] as LineDraft[] })
const formTotal = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_price) || 0), 0))

function newLine(): LineDraft {
  return { product_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: Number(shop.value?.tax_rate ?? 0) }
}

function openForm(): void {
  Object.assign(form, { customer_name: '', customer_email: '', issue_date: todayIso(), due_date: todayIso(), notes: '', lines: [newLine()] })
  formErrors.value = {}
  formModal.value = true
  loadProducts().catch(() => toast.error('Impossible de charger vos produits.'))
}

function onProductPicked(line: LineDraft): void {
  const product = products.value?.find(item => item.id === line.product_id)

  if (product) {
    line.description = product.name
    line.unit_price = Number(product.price)
  }
}

async function saveInvoice(): Promise<void> {
  saving.value = true
  formErrors.value = {}

  try {
    const { data } = await api<Resource<Invoice>>(`${apiBase.value}/invoices`, {
      method: 'POST',
      body: {
        customer_name: form.customer_name,
        customer_email: form.customer_email || null,
        issue_date: form.issue_date || null,
        due_date: form.due_date || null,
        notes: form.notes || null,
        lines: form.lines.map(line => ({ ...line, product_id: line.product_id || null })),
      },
    })
    formModal.value = false
    toast.success('Facture brouillon créée.')
    await navigateTo(`${basePath.value}/factures/${data.id}`)
  }
  catch (requestError) {
    formErrors.value = validationErrors(requestError)

    if (!Object.keys(formErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="invoicing" title="Facturation" subtitle="Factures numérotées, TVA et encaissements." icon="bi-receipt-cutoff" @retry="refresh()">
    <template #actions>
      <button type="button" class="btn btn-outline-primary" @click="openOrderPicker"><i class="bi bi-bag-check me-1" aria-hidden="true" />Facturer une commande</button>
      <button type="button" class="btn btn-primary" @click="openForm"><i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouvelle facture</button>
    </template>

    <div class="row g-3 mb-3">
      <div class="col-sm-6 col-lg-4">
        <div class="card h-100"><div class="card-body">
          <p class="small text-muted mb-1">Reste à encaisser (page affichée)</p>
          <p class="h4 fw-bold mb-0">{{ formatPrice(totals.due, currency) }}</p>
        </div></div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card h-100"><div class="card-body">
          <p class="small text-muted mb-1">Factures en retard (page affichée)</p>
          <p class="h4 fw-bold mb-0" :class="{ 'text-danger': totals.overdue }">{{ totals.overdue }}</p>
        </div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body d-flex flex-column flex-md-row gap-2">
        <label for="invoice-search" class="visually-hidden">Rechercher</label>
        <input id="invoice-search" v-model="search" type="search" class="form-control" placeholder="Numéro ou client…">
        <label for="invoice-status" class="visually-hidden">Statut</label>
        <select id="invoice-status" v-model="statusFilter" class="form-select" style="max-width: 16rem;">
          <option value="">Tous les statuts</option>
          <option v-for="item in INVOICE_STATUSES" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
      </div>
      <AppState v-if="listStatus === 'pending' && !invoices" loading text="Chargement des factures…" />
      <AppState v-else-if="listError" variant="error" title="Factures indisponibles" :text="errorMessage(listError)">
        <button type="button" class="btn btn-primary" @click="refreshList()">Réessayer</button>
      </AppState>
      <AppState v-else-if="!invoices?.data.length" icon="bi-receipt" title="Aucune facture" text="Facturez une commande de votre boutique ou créez une facture manuelle." />
      <div v-else class="table-responsive">
        <table class="table table-stack table-hover align-middle mb-0">
          <caption class="visually-hidden">Factures</caption>
          <thead class="table-light">
            <tr>
              <th scope="col">Facture</th>
              <th scope="col">Client</th>
              <th scope="col">Échéance</th>
              <th scope="col" class="text-end">Total</th>
              <th scope="col" class="text-end">Reste dû</th>
              <th scope="col">Statut</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="invoice in invoices.data" :key="invoice.id">
              <td>
                <NuxtLink :to="`${basePath}/factures/${invoice.id}`" class="fw-semibold">{{ invoice.number ?? 'Brouillon' }}</NuxtLink>
                <span v-if="invoice.order" class="d-block small text-muted">Commande {{ invoice.order.order_number }}</span>
              </td>
              <td data-label="Client">{{ invoice.customer_name }}</td>
              <td data-label="Échéance" :class="{ 'text-danger fw-semibold': invoice.is_overdue }">{{ formatDay(invoice.due_date) }}</td>
              <td data-label="Total" class="text-end">{{ formatPrice(invoice.total, invoice.currency) }}</td>
              <td data-label="Reste dû" class="text-end">{{ formatPrice(invoice.amount_due, invoice.currency) }}</td>
              <td data-label="Statut">
                <ErpStatusBadge :label="invoice.status_label" :variant="INVOICE_STATUS_VARIANTS[invoice.status]" />
                <ErpStatusBadge v-if="invoice.is_overdue" label="En retard" variant="danger" class="ms-1" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-3 pb-2"><AppPagination v-model:page="page" :meta="invoices?.meta" /></div>
    </div>

    <ErpModal v-model:open="orderModal" title="Facturer une commande" size="lg">
      <AppState v-if="loadingOrders" loading text="Recherche des commandes à facturer…" />
      <AppState v-else-if="!invoiceableOrders?.length" icon="bi-check2-circle" title="Tout est facturé" text="Aucune commande en attente de facture." />
      <ul v-else class="list-group">
        <li v-for="order in invoiceableOrders" :key="order.id" class="list-group-item d-flex justify-content-between align-items-center gap-2">
          <div>
            <span class="fw-semibold">{{ order.order_number }}</span> · {{ order.customer_name }}
            <span class="d-block small text-muted">{{ formatDate(order.created_at) }} · {{ formatPrice(order.total, order.currency) }}</span>
          </div>
          <button type="button" class="btn btn-sm btn-primary" :disabled="invoicingOrderId !== null" @click="invoiceOrder(order)">
            <span v-if="invoicingOrderId === order.id" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />Facturer
          </button>
        </li>
      </ul>
    </ErpModal>

    <ErpModal v-model:open="formModal" title="Nouvelle facture" size="xl">
      <form id="invoice-form" novalidate @submit.prevent="saveInvoice">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="invoice-customer" class="form-label">Client <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="invoice-customer" v-model="form.customer_name" type="text" maxlength="160" required class="form-control" :class="{ 'is-invalid': formErrors.customer_name }">
            <FieldError id="invoice-customer-error" :message="formErrors.customer_name" />
          </div>
          <div class="col-md-6">
            <label for="invoice-email" class="form-label">E-mail du client</label>
            <input id="invoice-email" v-model="form.customer_email" type="email" class="form-control" :class="{ 'is-invalid': formErrors.customer_email }">
            <FieldError id="invoice-email-error" :message="formErrors.customer_email" />
          </div>
          <div class="col-sm-6">
            <label for="invoice-issue" class="form-label">Date de facture</label>
            <input id="invoice-issue" v-model="form.issue_date" type="date" class="form-control" :class="{ 'is-invalid': formErrors.issue_date }">
            <FieldError id="invoice-issue-error" :message="formErrors.issue_date" />
          </div>
          <div class="col-sm-6">
            <label for="invoice-due" class="form-label">Échéance</label>
            <input id="invoice-due" v-model="form.due_date" type="date" class="form-control" :class="{ 'is-invalid': formErrors.due_date }">
            <FieldError id="invoice-due-error" :message="formErrors.due_date" />
          </div>
        </div>

        <fieldset>
          <legend class="h6 fw-bold">Lignes (prix TTC)</legend>
          <FieldError id="invoice-lines-error" :message="formErrors.lines" />
          <div v-for="(line, index) in form.lines" :key="index" class="row g-2 align-items-end border-bottom pb-2 mb-2">
            <div class="col-md-3">
              <label :for="`line-product-${index}`" class="form-label small">Produit (facultatif)</label>
              <select :id="`line-product-${index}`" v-model.number="line.product_id" class="form-select form-select-sm" @change="onProductPicked(line)">
                <option value="">Ligne libre</option>
                <option v-for="product in products ?? []" :key="product.id" :value="product.id">{{ product.name }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label :for="`line-description-${index}`" class="form-label small">Désignation</label>
              <input :id="`line-description-${index}`" v-model="line.description" type="text" class="form-control form-control-sm" :class="{ 'is-invalid': formErrors[`lines.${index}.description`] }">
            </div>
            <div class="col-4 col-md-1">
              <label :for="`line-qty-${index}`" class="form-label small">Qté</label>
              <input :id="`line-qty-${index}`" v-model.number="line.quantity" type="number" min="0.01" step="0.01" class="form-control form-control-sm" :class="{ 'is-invalid': formErrors[`lines.${index}.quantity`] }">
            </div>
            <div class="col-4 col-md-2">
              <label :for="`line-price-${index}`" class="form-label small">Prix unitaire TTC</label>
              <input :id="`line-price-${index}`" v-model.number="line.unit_price" type="number" step="1" class="form-control form-control-sm" :class="{ 'is-invalid': formErrors[`lines.${index}.unit_price`] }">
            </div>
            <div class="col-4 col-md-1">
              <label :for="`line-tax-${index}`" class="form-label small">TVA %</label>
              <input :id="`line-tax-${index}`" v-model.number="line.tax_rate" type="number" min="0" max="100" step="0.01" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex justify-content-between align-items-center">
              <span class="small fw-semibold">{{ formatPrice((line.quantity || 0) * (line.unit_price || 0), currency) }}</span>
              <button type="button" class="btn btn-sm btn-light text-danger" :disabled="form.lines.length === 1" :aria-label="`Retirer la ligne ${index + 1}`" @click="form.lines.splice(index, 1)"><i class="bi bi-x-lg" aria-hidden="true" /></button>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" @click="form.lines.push(newLine())"><i class="bi bi-plus me-1" aria-hidden="true" />Ajouter une ligne</button>
        </fieldset>

        <div class="mt-3">
          <label for="invoice-notes" class="form-label">Notes</label>
          <textarea id="invoice-notes" v-model="form.notes" rows="2" class="form-control" />
        </div>
      </form>
      <template #footer>
        <span class="me-auto fw-bold">Total TTC : {{ formatPrice(formTotal, currency) }}</span>
        <button type="button" class="btn btn-light" @click="formModal = false">Annuler</button>
        <button type="submit" form="invoice-form" class="btn btn-primary" :disabled="saving">Créer le brouillon</button>
      </template>
    </ErpModal>
  </ErpPage>
</template>

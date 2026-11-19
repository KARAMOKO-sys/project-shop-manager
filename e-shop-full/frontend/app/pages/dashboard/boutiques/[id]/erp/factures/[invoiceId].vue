<script setup lang="ts">
import type { Activity, Invoice, InvoicePaymentMethod, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = computed(() => String(route.params.id))
const invoiceId = computed(() => String(route.params.invoiceId))
const { shop, loading, error, refresh, isInstalled, apiBase, basePath } = useErpShop(shopId, 'invoicing')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)

const { data: invoice, status, error: invoiceError, refresh: refreshInvoice } = useAsyncData(
  () => `erp-invoice-${invoiceId.value}`,
  () => (enabled.value ? api<Resource<Invoice>>(`${apiBase.value}/invoices/${invoiceId.value}`).then(response => response.data) : Promise.resolve(null)),
  { server: false, watch: [enabled] },
)

const { data: activities, refresh: refreshActivities } = useAsyncData(
  () => `erp-invoice-activity-${invoiceId.value}`,
  () => (enabled.value
    ? api<{ data: Activity[] }>(`${apiBase.value}/activities${queryString({ subject_type: 'invoice', subject_id: invoiceId.value })}`).then(response => response.data)
    : Promise.resolve([] as Activity[])),
  { server: false, watch: [enabled] },
)

useSeoMeta({ title: () => (invoice.value?.number ? `Facture ${invoice.value.number}` : 'Facture brouillon') })

const busy = ref(false)

async function runAction(action: 'post' | 'cancel', success: string): Promise<void> {
  if (action === 'cancel' && !window.confirm('Annuler cette facture ? Cette action est définitive.')) {
    return
  }

  busy.value = true

  try {
    invoice.value = (await api<Resource<Invoice>>(`${apiBase.value}/invoices/${invoiceId.value}/${action}`, { method: 'POST' })).data
    toast.success(success)
    await refreshActivities()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    busy.value = false
  }
}

/* Payment */
const paymentModal = ref(false)
const paymentErrors = ref<Record<string, string>>({})
const payment = reactive({ amount: 0, method: 'cash' as InvoicePaymentMethod, paid_at: todayIso(), reference: '' })

function openPayment(): void {
  Object.assign(payment, { amount: invoice.value?.amount_due ?? 0, method: 'cash', paid_at: todayIso(), reference: '' })
  paymentErrors.value = {}
  paymentModal.value = true
}

async function savePayment(): Promise<void> {
  busy.value = true
  paymentErrors.value = {}

  try {
    invoice.value = (await api<Resource<Invoice>>(`${apiBase.value}/invoices/${invoiceId.value}/payments`, {
      method: 'POST',
      body: { ...payment, reference: payment.reference || null },
    })).data
    paymentModal.value = false
    toast.success('Paiement enregistré.')
    await refreshActivities()
  }
  catch (requestError) {
    paymentErrors.value = validationErrors(requestError)

    if (!Object.keys(paymentErrors.value).length) {
      toast.error(errorMessage(requestError))
    }
  }
  finally {
    busy.value = false
  }
}

function methodLabel(method: string): string {
  return INVOICE_PAYMENT_METHODS.find(item => item.value === method)?.label ?? method
}

function print(): void {
  window.print()
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="invoicing" :title="invoice?.number ? `Facture ${invoice.number}` : 'Facture brouillon'" icon="bi-receipt-cutoff" @retry="refresh()">
    <template #actions>
      <NuxtLink :to="`${basePath}/factures`" class="btn btn-light"><i class="bi bi-arrow-left me-1" aria-hidden="true" />Factures</NuxtLink>
      <template v-if="invoice">
        <button v-if="invoice.status === 'draft'" type="button" class="btn btn-primary" :disabled="busy" @click="runAction('post', 'Facture validée et numérotée.')">
          <i class="bi bi-check2-circle me-1" aria-hidden="true" />Valider
        </button>
        <button v-if="['posted', 'partially_paid'].includes(invoice.status)" type="button" class="btn btn-success" :disabled="busy" @click="openPayment">
          <i class="bi bi-cash-coin me-1" aria-hidden="true" />Enregistrer un paiement
        </button>
        <button type="button" class="btn btn-outline-primary" @click="print"><i class="bi bi-printer me-1" aria-hidden="true" />Imprimer</button>
        <button v-if="invoice.status !== 'cancelled' && !invoice.amount_paid" type="button" class="btn btn-outline-danger" :disabled="busy" @click="runAction('cancel', 'Facture annulée.')">Annuler</button>
      </template>
    </template>

    <AppState v-if="status === 'pending' && !invoice" loading text="Chargement de la facture…" />
    <AppState v-else-if="invoiceError || !invoice" variant="error" title="Facture introuvable" :text="errorMessage(invoiceError, 'Cette facture n’existe pas dans cette boutique.')">
      <button type="button" class="btn btn-primary" @click="refreshInvoice()">Réessayer</button>
    </AppState>
    <div v-else class="row g-4">
      <div class="col-lg-8">
        <article class="card invoice-sheet">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
              <div>
                <img v-if="shop?.logo_url" :src="shop.logo_url" alt="" style="max-height: 48px;" class="mb-2">
                <p class="h5 fw-bold mb-0">{{ shop?.name }}</p>
                <p v-if="shop?.delivery?.pickup_address" class="small text-muted mb-0">{{ shop.delivery.pickup_address }}</p>
              </div>
              <div class="text-end">
                <p class="h4 fw-bold mb-1">FACTURE</p>
                <p class="mb-0 fw-semibold">{{ invoice.number ?? 'Brouillon (non numérotée)' }}</p>
                <ErpStatusBadge class="d-print-none" :label="invoice.status_label" :variant="INVOICE_STATUS_VARIANTS[invoice.status]" />
              </div>
            </div>

            <div class="row mb-4 small">
              <div class="col-sm-6">
                <p class="text-muted mb-1">Facturé à</p>
                <p class="fw-semibold mb-0">{{ invoice.customer_name }}</p>
                <p v-if="invoice.customer_email" class="mb-0">{{ invoice.customer_email }}</p>
              </div>
              <div class="col-sm-6 text-sm-end">
                <p class="mb-0">Date : <strong>{{ formatDay(invoice.issue_date) }}</strong></p>
                <p class="mb-0">Échéance : <strong :class="{ 'text-danger': invoice.is_overdue }">{{ formatDay(invoice.due_date) }}</strong></p>
                <p v-if="invoice.order" class="mb-0">Commande : {{ invoice.order.order_number }}</p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table align-middle">
                <caption class="visually-hidden">Lignes de la facture</caption>
                <thead class="table-light">
                  <tr>
                    <th scope="col">Désignation</th>
                    <th scope="col" class="text-end">Qté</th>
                    <th scope="col" class="text-end">Prix TTC</th>
                    <th scope="col" class="text-end">TVA</th>
                    <th scope="col" class="text-end">Total TTC</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in invoice.lines ?? []" :key="line.id">
                    <td>{{ line.description }}</td>
                    <td class="text-end">{{ formatNumber(line.quantity, 2) }}</td>
                    <td class="text-end">{{ formatPrice(line.unit_price, invoice.currency) }}</td>
                    <td class="text-end">{{ formatNumber(line.tax_rate, 2) }} %</td>
                    <td class="text-end">{{ formatPrice(line.total, invoice.currency) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr><th scope="row" colspan="4" class="text-end fw-normal">Total HT</th><td class="text-end">{{ formatPrice(invoice.subtotal, invoice.currency) }}</td></tr>
                  <tr><th scope="row" colspan="4" class="text-end fw-normal">TVA</th><td class="text-end">{{ formatPrice(invoice.tax_amount, invoice.currency) }}</td></tr>
                  <tr><th scope="row" colspan="4" class="text-end">Total TTC</th><td class="text-end fw-bold">{{ formatPrice(invoice.total, invoice.currency) }}</td></tr>
                  <tr v-if="invoice.amount_paid"><th scope="row" colspan="4" class="text-end fw-normal">Déjà payé</th><td class="text-end">{{ formatPrice(invoice.amount_paid, invoice.currency) }}</td></tr>
                  <tr><th scope="row" colspan="4" class="text-end">Reste à payer</th><td class="text-end fw-bold">{{ formatPrice(invoice.amount_due, invoice.currency) }}</td></tr>
                </tfoot>
              </table>
            </div>
            <p v-if="invoice.notes" class="small text-muted mb-0">{{ invoice.notes }}</p>
          </div>
        </article>
      </div>

      <div class="col-lg-4 d-print-none">
        <div class="card mb-4">
          <div class="card-body">
            <h2 class="h6 fw-bold">Paiements</h2>
            <ul v-if="invoice.payments?.length" class="list-unstyled mb-0 small">
              <li v-for="item in invoice.payments" :key="item.id" class="d-flex justify-content-between border-bottom py-2">
                <span>{{ formatDay(item.paid_at) }} · {{ methodLabel(item.method) }}<span v-if="item.reference" class="d-block text-muted">{{ item.reference }}</span></span>
                <strong>{{ formatPrice(item.amount, invoice.currency) }}</strong>
              </li>
            </ul>
            <p v-else class="small text-muted mb-0">Aucun paiement enregistré.</p>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <h2 class="h6 fw-bold">Historique</h2>
            <ErpActivityFeed :activities="activities ?? []" />
          </div>
        </div>
      </div>
    </div>

    <ErpModal v-model:open="paymentModal" title="Enregistrer un paiement">
      <form id="payment-form" novalidate @submit.prevent="savePayment">
        <div class="mb-3">
          <label for="payment-amount" class="form-label">Montant</label>
          <input id="payment-amount" v-model.number="payment.amount" type="number" min="1" step="1" class="form-control" :class="{ 'is-invalid': paymentErrors.amount }" aria-describedby="payment-amount-help">
          <div id="payment-amount-help" class="form-text">Reste à payer : {{ formatPrice(invoice?.amount_due, invoice?.currency) }}</div>
          <FieldError id="payment-amount-error" :message="paymentErrors.amount" />
        </div>
        <div class="mb-3">
          <label for="payment-method" class="form-label">Moyen de paiement</label>
          <select id="payment-method" v-model="payment.method" class="form-select">
            <option v-for="item in INVOICE_PAYMENT_METHODS" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="payment-date" class="form-label">Date</label>
          <input id="payment-date" v-model="payment.paid_at" type="date" class="form-control" :class="{ 'is-invalid': paymentErrors.paid_at }">
          <FieldError id="payment-date-error" :message="paymentErrors.paid_at" />
        </div>
        <div>
          <label for="payment-reference" class="form-label">Référence (n° de transaction Mobile Money…)</label>
          <input id="payment-reference" v-model="payment.reference" type="text" maxlength="120" class="form-control">
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="paymentModal = false">Annuler</button>
        <button type="submit" form="payment-form" class="btn btn-success" :disabled="busy">Enregistrer</button>
      </template>
    </ErpModal>
  </ErpPage>
</template>

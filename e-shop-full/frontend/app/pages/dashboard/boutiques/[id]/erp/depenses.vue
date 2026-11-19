<script setup lang="ts">
import type { Expense, ExpenseCategory, ExpenseList, Resource, Supplier } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Dépenses' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = computed(() => String(route.params.id))
const { shop, loading, error, refresh, isInstalled, hasModule, apiBase } = useErpShop(shopId, 'expenses')

const currency = computed(() => shop.value?.currency ?? 'XOF')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)

/* Filters: current month by default */
function monthStart(): string {
  return `${todayIso().slice(0, 7)}-01`
}

const page = ref(1)
const category = ref<ExpenseCategory | ''>('')
const from = ref(monthStart())
const to = ref(todayIso())
watch([category, from, to], () => (page.value = 1))

const { data: expenses, status: listStatus, error: listError, refresh: refreshList } = useAsyncData(
  () => `erp-expenses-${shopId.value}`,
  () => (enabled.value
    ? api<ExpenseList>(`${apiBase.value}/expenses${queryString({ page: page.value, category: category.value, from: from.value, to: to.value })}`)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, page, category, from, to] },
)

const breakdown = computed(() => {
  const byCategory = expenses.value?.summary.by_category ?? {}
  const total = expenses.value?.summary.total || 0

  return EXPENSE_CATEGORIES
    .map(item => ({ ...item, amount: Number(byCategory[item.value] ?? 0) }))
    .filter(item => item.amount > 0)
    .sort((a, b) => b.amount - a.amount)
    .map(item => ({ ...item, share: total ? Math.round(item.amount / total * 100) : 0 }))
})

const { data: suppliers } = useAsyncData(
  () => `erp-expense-suppliers-${shopId.value}`,
  () => (enabled.value && hasModule('purchase')
    ? api<Resource<Supplier[]>>(`${apiBase.value}/suppliers`).then(response => response.data)
    : Promise.resolve([] as Supplier[])),
  { server: false, watch: [enabled] },
)

/* Form */
const modal = ref(false)
const saving = ref(false)
const formErrors = ref<Record<string, string>>({})
const form = reactive({
  id: null as number | null,
  category: 'other' as ExpenseCategory,
  label: '',
  amount: 0,
  spent_at: todayIso(),
  payment_method: '',
  reference: '',
  notes: '',
  supplier_id: '' as number | '',
})

function openForm(expense?: Expense): void {
  Object.assign(form, expense
    ? { id: expense.id, category: expense.category, label: expense.label, amount: expense.amount, spent_at: expense.spent_at.slice(0, 10), payment_method: expense.payment_method ?? '', reference: expense.reference ?? '', notes: expense.notes ?? '', supplier_id: expense.supplier?.id ?? '' }
    : { id: null, category: 'other', label: '', amount: 0, spent_at: todayIso(), payment_method: '', reference: '', notes: '', supplier_id: '' })
  formErrors.value = {}
  modal.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formErrors.value = {}
  const { id, ...fields } = form
  const body = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]))

  try {
    await api(id ? `${apiBase.value}/expenses/${id}` : `${apiBase.value}/expenses`, { method: id ? 'PUT' : 'POST', body })
    modal.value = false
    toast.success('Dépense enregistrée.')
    await refreshList()
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

async function remove(expense: Expense): Promise<void> {
  if (!window.confirm(`Supprimer la dépense « ${expense.label} » ?`)) {
    return
  }

  try {
    await api(`${apiBase.value}/expenses/${expense.id}`, { method: 'DELETE' })
    toast.success('Dépense supprimée.')
    await refreshList()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="expenses" title="Dépenses" subtitle="Vos charges, pour connaître votre bénéfice réel." icon="bi-wallet2" @retry="refresh()">
    <template #actions>
      <button type="button" class="btn btn-primary" @click="openForm()"><i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouvelle dépense</button>
    </template>

    <div class="card mb-3">
      <div class="card-body row g-2 align-items-end">
        <div class="col-sm-6 col-lg-4">
          <label for="expense-filter-category" class="form-label small">Catégorie</label>
          <select id="expense-filter-category" v-model="category" class="form-select">
            <option value="">Toutes les catégories</option>
            <option v-for="item in EXPENSE_CATEGORIES" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label for="expense-filter-from" class="form-label small">Du</label>
          <input id="expense-filter-from" v-model="from" type="date" class="form-control">
        </div>
        <div class="col-6 col-lg-3">
          <label for="expense-filter-to" class="form-label small">Au</label>
          <input id="expense-filter-to" v-model="to" type="date" class="form-control">
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-4 order-lg-2">
        <div class="card">
          <div class="card-body">
            <p class="small text-muted mb-1">Total de la période</p>
            <p class="h3 fw-bold mb-3">{{ formatPrice(expenses?.summary.total ?? 0, currency) }}</p>
            <ul v-if="breakdown.length" class="list-unstyled mb-0">
              <li v-for="item in breakdown" :key="item.value" class="mb-2">
                <div class="d-flex justify-content-between small">
                  <span><i class="bi me-1" :class="item.icon" aria-hidden="true" />{{ item.label }}</span>
                  <span class="fw-semibold">{{ formatPrice(item.amount, currency) }}</span>
                </div>
                <div class="progress" role="progressbar" style="height: 6px;" :aria-label="`${item.label} : ${item.share} %`" :aria-valuenow="item.share" aria-valuemin="0" aria-valuemax="100">
                  <div class="progress-bar" :style="{ width: `${item.share}%` }" />
                </div>
              </li>
            </ul>
            <p v-else class="small text-muted mb-0">Aucune dépense sur la période.</p>
          </div>
        </div>
      </div>

      <div class="col-lg-8 order-lg-1">
        <div class="card">
          <AppState v-if="listStatus === 'pending' && !expenses" loading text="Chargement des dépenses…" />
          <AppState v-else-if="listError" variant="error" title="Dépenses indisponibles" :text="errorMessage(listError)">
            <button type="button" class="btn btn-primary" @click="refreshList()">Réessayer</button>
          </AppState>
          <AppState v-else-if="!expenses?.data.length" icon="bi-wallet2" title="Aucune dépense" text="Enregistrez loyer, transport, salaires… pour voir votre bénéfice net dans le tableau de bord.">
            <button type="button" class="btn btn-primary" @click="openForm()">Ajouter une dépense</button>
          </AppState>
          <div v-else class="table-responsive">
            <table class="table table-stack align-middle mb-0">
              <caption class="visually-hidden">Dépenses</caption>
              <thead class="table-light">
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Libellé</th>
                  <th scope="col">Catégorie</th>
                  <th scope="col" class="text-end">Montant</th>
                  <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="expense in expenses.data" :key="expense.id">
                  <td data-label="Date" class="text-nowrap">{{ formatDay(expense.spent_at) }}</td>
                  <td>
                    <span class="fw-semibold">{{ expense.label }}</span>
                    <span v-if="expense.supplier || expense.reference" class="d-block small text-muted">{{ [expense.supplier?.name, expense.reference].filter(Boolean).join(' · ') }}</span>
                  </td>
                  <td data-label="Catégorie">{{ expense.category_label }}</td>
                  <td data-label="Montant" class="text-end fw-semibold">{{ formatPrice(expense.amount, expense.currency) }}</td>
                  <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-light" :aria-label="`Modifier ${expense.label}`" @click="openForm(expense)"><i class="bi bi-pencil" aria-hidden="true" /></button>
                    <button type="button" class="btn btn-sm btn-light text-danger ms-1" :aria-label="`Supprimer ${expense.label}`" @click="remove(expense)"><i class="bi bi-trash" aria-hidden="true" /></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-3 pb-2"><AppPagination v-model:page="page" :meta="expenses?.meta" /></div>
        </div>
      </div>
    </div>

    <ErpModal v-model:open="modal" :title="form.id ? 'Modifier la dépense' : 'Nouvelle dépense'" size="lg">
      <form id="expense-form" novalidate @submit.prevent="save">
        <div class="row g-3">
          <div class="col-md-8">
            <label for="expense-label" class="form-label">Libellé <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="expense-label" v-model="form.label" type="text" maxlength="160" required class="form-control" :class="{ 'is-invalid': formErrors.label }" placeholder="Ex. : loyer de novembre">
            <FieldError id="expense-label-error" :message="formErrors.label" />
          </div>
          <div class="col-md-4">
            <label for="expense-amount" class="form-label">Montant <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="expense-amount" v-model.number="form.amount" type="number" min="1" step="1" required class="form-control" :class="{ 'is-invalid': formErrors.amount }">
            <FieldError id="expense-amount-error" :message="formErrors.amount" />
          </div>
          <div class="col-md-6">
            <label for="expense-category" class="form-label">Catégorie</label>
            <select id="expense-category" v-model="form.category" class="form-select" :class="{ 'is-invalid': formErrors.category }">
              <option v-for="item in EXPENSE_CATEGORIES" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
            <FieldError id="expense-category-error" :message="formErrors.category" />
          </div>
          <div class="col-md-6">
            <label for="expense-date" class="form-label">Date</label>
            <input id="expense-date" v-model="form.spent_at" type="date" class="form-control" :class="{ 'is-invalid': formErrors.spent_at }">
            <FieldError id="expense-date-error" :message="formErrors.spent_at" />
          </div>
          <div class="col-md-6">
            <label for="expense-method" class="form-label">Moyen de paiement</label>
            <select id="expense-method" v-model="form.payment_method" class="form-select">
              <option value="">—</option>
              <option v-for="item in INVOICE_PAYMENT_METHODS" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="expense-reference" class="form-label">Référence / n° de reçu</label>
            <input id="expense-reference" v-model="form.reference" type="text" maxlength="120" class="form-control">
          </div>
          <div v-if="suppliers?.length" class="col-12">
            <label for="expense-supplier" class="form-label">Fournisseur</label>
            <select id="expense-supplier" v-model.number="form.supplier_id" class="form-select" :class="{ 'is-invalid': formErrors.supplier_id }">
              <option value="">Aucun</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <FieldError id="expense-supplier-error" :message="formErrors.supplier_id" />
          </div>
          <div class="col-12">
            <label for="expense-notes" class="form-label">Notes</label>
            <textarea id="expense-notes" v-model="form.notes" rows="2" class="form-control" />
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="modal = false">Annuler</button>
        <button type="submit" form="expense-form" class="btn btn-primary" :disabled="saving">Enregistrer</button>
      </template>
    </ErpModal>
  </ErpPage>
</template>

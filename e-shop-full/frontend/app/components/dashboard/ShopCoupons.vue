<script setup lang="ts">
import type { Coupon, CouponPayload, CouponStatus, CouponType, Resource, Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const api = useApi()
const toast = useToast()

const COUPON_TYPES: { value: CouponType, label: string }[] = [
  { value: 'percentage', label: 'Pourcentage (%)' },
  { value: 'fixed', label: 'Montant fixe' },
]

const { data: coupons, status, error, refresh } = useAsyncData(
  `shop-coupons-${props.shop.id}`,
  () => api<Resource<Coupon[]>>(`/shops/${props.shop.id}/coupons`).then(response => response.data),
  { server: false },
)

const showForm = ref(false)
const saving = ref(false)
const busy = ref<number | null>(null)
const errors = ref<Record<string, string>>({})

function emptyForm() {
  return {
    code: '',
    type: 'percentage' as CouponType,
    value: null as number | null,
    min_order_amount: null as number | null,
    starts_at: '',
    ends_at: '',
    usage_limit: null as number | null,
    status: 'active' as CouponStatus,
  }
}

const form = reactive(emptyForm())
const currencyLabel = computed(() => (['XOF', 'XAF'].includes(props.shop.currency) ? 'FCFA' : props.shop.currency))

function openForm(): void {
  Object.assign(form, emptyForm())
  errors.value = {}
  showForm.value = true
  nextTick(() => document.getElementById('coupon-code-input')?.focus())
}

function validity(coupon: Coupon): string {
  if (coupon.starts_at && coupon.ends_at) {
    return `Du ${formatShortDate(coupon.starts_at)} au ${formatShortDate(coupon.ends_at)}`
  }

  if (coupon.starts_at) {
    return `À partir du ${formatShortDate(coupon.starts_at)}`
  }

  if (coupon.ends_at) {
    return `Jusqu’au ${formatShortDate(coupon.ends_at)}`
  }

  return 'Sans limite de date'
}

function usage(coupon: Coupon): string {
  return `${formatNumber(coupon.times_used)} / ${coupon.usage_limit > 0 ? formatNumber(coupon.usage_limit) : 'illimité'}`
}

function replaceCoupon(updated: Coupon): void {
  coupons.value = (coupons.value ?? []).map(item => (item.id === updated.id ? updated : item))
}

async function create(): Promise<void> {
  saving.value = true
  errors.value = {}

  const body: CouponPayload = {
    code: form.code.trim().toUpperCase(),
    type: form.type,
    value: Number(form.value ?? 0),
    min_order_amount: form.min_order_amount || 0,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
    usage_limit: form.usage_limit || 0,
    status: form.status,
  }

  try {
    const { data } = await api<Resource<Coupon>>(`/shops/${props.shop.id}/coupons`, { method: 'POST', body })
    coupons.value = [data, ...(coupons.value ?? [])]
    showForm.value = false
    toast.success(`Code promo ${data.code} créé.`)
  }
  catch (requestError) {
    errors.value = validationErrors(requestError)

    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(requestError, 'Le code promo n’a pas pu être créé.'))
    }
  }
  finally {
    saving.value = false
  }
}

async function toggle(coupon: Coupon): Promise<void> {
  const next: CouponStatus = coupon.status === 'active' ? 'inactive' : 'active'
  busy.value = coupon.id

  try {
    const { data } = await api<Resource<Coupon>>(`/shops/${props.shop.id}/coupons/${coupon.id}`, { method: 'PUT', body: { status: next } })
    replaceCoupon(data)
    toast.success(`Code ${data.code} ${next === 'active' ? 'activé' : 'désactivé'}.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'Le statut n’a pas pu être modifié.'))
  }
  finally {
    busy.value = null
  }
}

async function destroy(coupon: Coupon): Promise<void> {
  if (!window.confirm(`Supprimer définitivement le code promo « ${coupon.code} » ?`)) {
    return
  }

  busy.value = coupon.id

  try {
    await api(`/shops/${props.shop.id}/coupons/${coupon.id}`, { method: 'DELETE' })
    coupons.value = (coupons.value ?? []).filter(item => item.id !== coupon.id)
    toast.success('Code promo supprimé.')
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La suppression a échoué.'))
  }
  finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="d-grid gap-4">
    <form v-if="showForm" class="card p-4" novalidate aria-labelledby="coupon-form-title" @submit.prevent="create">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 id="coupon-form-title" class="h5 fw-bold mb-0">Nouveau code promo</h2>
        <button type="button" class="btn-close" aria-label="Fermer le formulaire" @click="showForm = false" />
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label for="coupon-code-input" class="form-label">Code <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="coupon-code-input" v-model="form.code" type="text" class="form-control text-uppercase" :class="{ 'is-invalid': errors.code }" required maxlength="40" placeholder="Ex. : BIENVENUE10" autocomplete="off" aria-describedby="coupon-code-input-error">
          <FieldError id="coupon-code-input-error" :message="errors.code" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-type" class="form-label">Type de réduction</label>
          <select id="coupon-type" v-model="form.type" class="form-select" :class="{ 'is-invalid': errors.type }" aria-describedby="coupon-type-error">
            <option v-for="option in COUPON_TYPES" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <FieldError id="coupon-type-error" :message="errors.type" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-value" class="form-label">Valeur ({{ form.type === 'percentage' ? '%' : currencyLabel }}) <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="coupon-value" v-model.number="form.value" type="number" min="0" :max="form.type === 'percentage' ? 100 : undefined" step="any" inputmode="decimal" class="form-control" :class="{ 'is-invalid': errors.value }" required aria-describedby="coupon-value-error">
          <FieldError id="coupon-value-error" :message="errors.value" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-min" class="form-label">Minimum d’achat ({{ currencyLabel }})</label>
          <input id="coupon-min" v-model.number="form.min_order_amount" type="number" min="0" step="any" inputmode="decimal" class="form-control" :class="{ 'is-invalid': errors.min_order_amount }" aria-describedby="coupon-min-error">
          <FieldError id="coupon-min-error" :message="errors.min_order_amount" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-starts" class="form-label">Début</label>
          <input id="coupon-starts" v-model="form.starts_at" type="date" class="form-control" :class="{ 'is-invalid': errors.starts_at }" aria-describedby="coupon-starts-error">
          <FieldError id="coupon-starts-error" :message="errors.starts_at" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-ends" class="form-label">Fin</label>
          <input id="coupon-ends" v-model="form.ends_at" type="date" class="form-control" :class="{ 'is-invalid': errors.ends_at }" :min="form.starts_at || undefined" aria-describedby="coupon-ends-error">
          <FieldError id="coupon-ends-error" :message="errors.ends_at" />
        </div>
        <div class="col-sm-6 col-md-4">
          <label for="coupon-limit" class="form-label">Nombre d’utilisations max.</label>
          <input id="coupon-limit" v-model.number="form.usage_limit" type="number" min="0" step="1" inputmode="numeric" class="form-control" :class="{ 'is-invalid': errors.usage_limit }" placeholder="Illimité" aria-describedby="coupon-limit-help coupon-limit-error">
          <div id="coupon-limit-help" class="form-text">Laissez vide ou 0 pour un usage illimité.</div>
          <FieldError id="coupon-limit-error" :message="errors.usage_limit" />
        </div>
        <div class="col-sm-6 col-md-4 d-flex align-items-center">
          <div class="form-check form-switch mt-md-3">
            <input id="coupon-active" class="form-check-input" type="checkbox" role="switch" :checked="form.status === 'active'" @change="form.status = ($event.target as HTMLInputElement).checked ? 'active' : 'inactive'">
            <label for="coupon-active" class="form-check-label">Actif dès sa création</label>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" @click="showForm = false">Annuler</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          Créer le code promo
        </button>
      </div>
    </form>

    <div class="card">
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h2 class="h5 fw-bold mb-0">Codes promo <span v-if="coupons" class="text-muted fw-normal">({{ coupons.length }})</span></h2>
          <p class="small text-muted mb-0">Offrez des réductions à vos clients pour booster vos ventes.</p>
        </div>
        <button v-if="!showForm" type="button" class="btn btn-primary btn-sm" @click="openForm">
          <i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouveau code promo
        </button>
      </div>
      <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des codes promo…" />
      <AppState v-else-if="error" variant="error" title="Impossible de charger les codes promo" :text="errorMessage(error)">
        <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
      </AppState>
      <AppState v-else-if="!coupons?.length" icon="bi-ticket-perforated" title="Aucun code promo" text="Créez un code comme BIENVENUE10 pour offrir 10 % à vos nouveaux clients.">
        <button v-if="!showForm" type="button" class="btn btn-primary" @click="openForm">
          <i class="bi bi-plus-lg me-1" aria-hidden="true" />Créer mon premier code
        </button>
      </AppState>
      <div v-else class="table-responsive">
        <table class="table align-middle mb-0">
          <caption class="visually-hidden">Codes promo de la boutique</caption>
          <thead class="table-light">
            <tr>
              <th scope="col">Code</th>
              <th scope="col">Réduction</th>
              <th scope="col">Minimum</th>
              <th scope="col">Validité</th>
              <th scope="col">Utilisations</th>
              <th scope="col">Statut</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="coupon in coupons" :key="coupon.id">
              <td><code class="fw-bold fs-6">{{ coupon.code }}</code></td>
              <td class="text-nowrap fw-semibold">{{ formatCouponValue(coupon, shop.currency) }}</td>
              <td class="text-nowrap">
                <PriceTag v-if="coupon.min_order_amount > 0" :amount="coupon.min_order_amount" :currency="shop.currency" />
                <span v-else class="text-muted">Aucun</span>
              </td>
              <td class="small">{{ validity(coupon) }}</td>
              <td class="text-nowrap">{{ usage(coupon) }}</td>
              <td>
                <span class="badge rounded-pill" :class="coupon.status === 'active' ? 'text-bg-success' : 'text-bg-secondary'">
                  {{ coupon.status === 'active' ? 'Actif' : 'Inactif' }}
                </span>
                <span v-if="coupon.is_redeemable" class="d-block small text-success mt-1"><i class="bi bi-check-circle me-1" aria-hidden="true" />Utilisable</span>
                <span v-else class="d-block small text-muted mt-1"><i class="bi bi-slash-circle me-1" aria-hidden="true" />Non utilisable</span>
              </td>
              <td class="text-end text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-light"
                  :disabled="busy === coupon.id"
                  :aria-label="coupon.status === 'active' ? `Désactiver ${coupon.code}` : `Activer ${coupon.code}`"
                  :title="coupon.status === 'active' ? 'Désactiver' : 'Activer'"
                  @click="toggle(coupon)"
                >
                  <span v-if="busy === coupon.id" class="spinner-border spinner-border-sm" aria-hidden="true" />
                  <i v-else class="bi" :class="coupon.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'" aria-hidden="true" />
                </button>
                <button type="button" class="btn btn-sm btn-light text-danger ms-1" :disabled="busy === coupon.id" :aria-label="`Supprimer ${coupon.code}`" title="Supprimer" @click="destroy(coupon)">
                  <i class="bi bi-trash" aria-hidden="true" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

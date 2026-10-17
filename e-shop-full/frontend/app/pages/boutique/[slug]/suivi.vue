<script setup lang="ts">
import type { Order, Resource } from '~/types'

definePageMeta({ layout: 'storefront' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const slug = String(route.params.slug)

const { data: shop } = await useStorefrontShop(slug)

useSeoMeta({ title: () => `Suivi de commande — ${shop.value?.name ?? 'Boutique'}`, robots: 'noindex' })

const form = reactive({
  orderNumber: typeof route.query.commande === 'string' ? route.query.commande : '',
  email: typeof route.query.email === 'string' ? route.query.email : '',
})
const errors = ref<Record<string, string>>({})
const searchError = ref('')
const searching = ref(false)
const order = ref<Order | null>(null)

const isCancelled = computed(() => order.value?.status === 'cancelled')
const currentStep = computed(() => (order.value ? ORDER_TIMELINE.indexOf(order.value.status) : -1))

function normalizeOrderNumber(value: string): string {
  return value.trim().replace(/^#/, '').toUpperCase()
}

async function search(): Promise<void> {
  const orderNumber = normalizeOrderNumber(form.orderNumber)
  const email = form.email.trim()
  errors.value = {}
  searchError.value = ''

  if (!orderNumber) {
    errors.value.orderNumber = 'Indiquez votre numéro de commande.'
  }

  if (!email) {
    errors.value.email = 'Indiquez l’adresse e-mail utilisée lors de la commande.'
  }

  if (Object.keys(errors.value).length) {
    return
  }

  searching.value = true
  order.value = null

  try {
    const { data } = await api<Resource<Order>>(`/storefront/${slug}/orders/${encodeURIComponent(orderNumber)}`, { query: { email } })
    order.value = data
    await router.replace({ query: { ...route.query, commande: orderNumber, email } })
  }
  catch (error) {
    const fieldErrors = validationErrors(error)

    if (fieldErrors.email) {
      errors.value.email = fieldErrors.email
    }
    else if (statusOf(error) === 404) {
      searchError.value = 'Aucune commande ne correspond à ce numéro et à cette adresse e-mail. Vérifiez vos informations.'
    }
    else {
      searchError.value = errorMessage(error, 'Impossible de retrouver votre commande pour le moment.')
    }
  }
  finally {
    searching.value = false
  }
}

onMounted(() => {
  if (form.orderNumber && form.email) {
    search()
  }
})
</script>

<template>
  <div class="container py-4" style="max-width: 860px;">
    <h1 class="h3 fw-bold mb-1">Suivre ma commande</h1>
    <p class="text-muted mb-4">Saisissez votre numéro de commande et l’adresse e-mail utilisée lors de l’achat.</p>

    <form class="card p-4 mb-4" novalidate aria-label="Rechercher une commande" @submit.prevent="search">
      <div class="row g-3 align-items-start">
        <div class="col-md-5">
          <label for="tracking-number" class="form-label">Numéro de commande</label>
          <input id="tracking-number" v-model="form.orderNumber" type="text" class="form-control" :class="{ 'is-invalid': errors.orderNumber }" placeholder="Ex. : ORD-20261006-ABCD" autocomplete="off" required aria-describedby="tracking-number-error">
          <FieldError id="tracking-number-error" :message="errors.orderNumber" />
        </div>
        <div class="col-md-4">
          <label for="tracking-email" class="form-label">E-mail</label>
          <input id="tracking-email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" autocomplete="email" required aria-describedby="tracking-email-error">
          <FieldError id="tracking-email-error" :message="errors.email" />
        </div>
        <div class="col-md-3">
          <span class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</span>
          <button type="submit" class="btn btn-primary w-100" :disabled="searching">
            <span v-if="searching" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
            <i v-else class="bi bi-search me-1" aria-hidden="true" />Rechercher
          </button>
        </div>
      </div>
    </form>

    <div aria-live="polite">
      <AppState v-if="searching" loading text="Recherche de votre commande…" />
      <div v-else-if="searchError" class="alert alert-warning d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true" />
        <span>{{ searchError }}</span>
      </div>
      <section v-else-if="order" class="card p-4" aria-labelledby="tracking-result-title">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
          <div>
            <h2 id="tracking-result-title" class="h5 fw-bold mb-0">Commande #{{ order.order_number }}</h2>
            <p class="small text-muted mb-0">Passée le {{ formatDate(order.created_at) }}</p>
          </div>
          <span class="badge rounded-pill fs-6" :class="`text-bg-${orderStatusMeta(order.status).variant}`">{{ orderStatusMeta(order.status).label }}</span>
        </div>

        <div v-if="isCancelled" class="alert alert-danger d-flex gap-2" role="status">
          <i class="bi bi-x-octagon" aria-hidden="true" />
          <span>Cette commande a été annulée. Contactez la boutique si vous pensez qu’il s’agit d’une erreur.</span>
        </div>
        <ol v-else class="order-timeline list-unstyled mb-4" aria-label="Avancement de la commande">
          <li
            v-for="(step, index) in ORDER_TIMELINE"
            :key="step"
            class="order-timeline-step"
            :class="{ 'is-done': index < currentStep, 'is-current': index === currentStep }"
            :aria-current="index === currentStep ? 'step' : undefined"
          >
            <span class="order-timeline-dot" aria-hidden="true">
              <i v-if="index <= currentStep" class="bi bi-check-lg" />
            </span>
            <span class="order-timeline-label">{{ orderStatusMeta(step).label }}</span>
            <span class="visually-hidden">{{ index < currentStep ? '(terminé)' : index === currentStep ? '(étape actuelle)' : '(à venir)' }}</span>
          </li>
        </ol>

        <h3 class="h6 fw-bold">Articles</h3>
        <ul class="list-unstyled d-grid gap-2 small mb-3">
          <li v-for="item in order.items" :key="item.product_id" class="d-flex justify-content-between gap-2">
            <span>{{ item.quantity }} × {{ item.product_name }}</span>
            <PriceTag :amount="item.total_price" :currency="order.currency" />
          </li>
        </ul>
        <dl class="small border-top pt-3 mb-0">
          <div class="d-flex justify-content-between">
            <dt class="fw-normal">Sous-total</dt>
            <dd class="mb-1"><PriceTag :amount="order.subtotal" :currency="order.currency" /></dd>
          </div>
          <div class="d-flex justify-content-between">
            <dt class="fw-normal">Livraison</dt>
            <dd class="mb-1"><PriceTag :amount="order.shipping_amount" :currency="order.currency" /></dd>
          </div>
          <div v-if="order.discount_amount" class="d-flex justify-content-between text-success">
            <dt class="fw-normal">Remise</dt>
            <dd class="mb-1">−{{ formatPrice(order.discount_amount, order.currency) }}</dd>
          </div>
          <div class="d-flex justify-content-between fs-5 fw-bold pt-2">
            <dt>Total</dt>
            <dd class="mb-0"><PriceTag :amount="order.total" :currency="order.currency" /></dd>
          </div>
          <div class="d-flex justify-content-between text-muted pt-2">
            <dt class="fw-normal">Paiement</dt>
            <dd class="mb-0">{{ paymentMethodLabel(order.payment_method) }} · {{ paymentStatusLabel(order.payment_status) }}</dd>
          </div>
        </dl>
      </section>
    </div>

    <p class="mt-4 mb-0">
      <NuxtLink :to="`/boutique/${slug}`"><i class="bi bi-arrow-left me-1" aria-hidden="true" />Retour à la boutique</NuxtLink>
    </p>
  </div>
</template>

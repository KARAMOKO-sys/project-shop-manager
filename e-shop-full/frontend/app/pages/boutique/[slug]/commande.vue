<script setup lang="ts">
import type { CouponCheck, DeliveryMethod, Order, PaymentMethod, Resource } from '~/types'

definePageMeta({ layout: 'storefront' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const slug = String(route.params.slug)

const { data: shop } = await useStorefrontShop(slug)
const { items, subtotal, clear } = useCart(slug)

useSeoMeta({ title: () => `Commande — ${shop.value?.name ?? 'Boutique'}`, robots: 'noindex' })

const customer = reactive({ first_name: '', last_name: '', email: '', phone: '' })
const paymentMethod = ref<PaymentMethod>('cash_on_delivery')
const paymentOptions = computed(() => PAYMENT_METHODS.filter(method => shop.value?.payment_methods.includes(method.value)))
const deliveryMethod = ref<DeliveryMethod>(shop.value?.delivery?.methods[0] ?? 'delivery')
const address = reactive({ street: '', city: '' })
const couponCode = ref('')
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const submitting = ref(false)
const order = ref<Order | null>(null)
const appliedCoupon = ref<CouponCheck | null>(null)
const couponError = ref('')
const checkingCoupon = ref(false)

const currency = computed(() => shop.value?.currency ?? items.value[0]?.currency ?? 'XOF')

const CUSTOMER_FIELDS = [
  { key: 'first_name', label: 'Prénom', type: 'text', autocomplete: 'given-name', col: 'col-sm-6' },
  { key: 'last_name', label: 'Nom', type: 'text', autocomplete: 'family-name', col: 'col-sm-6' },
  { key: 'phone', label: 'Téléphone', type: 'tel', autocomplete: 'tel', col: 'col-sm-6' },
  { key: 'email', label: 'E-mail', type: 'email', autocomplete: 'email', col: 'col-sm-6' },
] as const

const discount = computed(() => Math.min(subtotal.value, Number(appliedCoupon.value?.discount_amount ?? 0)))
/** Mirrors Shop::deliveryFeeFor(): flat fee, free from the threshold, pickup always free. */
const shippingFee = computed(() => {
  const delivery = shop.value?.delivery

  if (!delivery || deliveryMethod.value === 'pickup') {
    return 0
  }

  const amount = subtotal.value - discount.value

  return delivery.free_threshold !== null && amount >= delivery.free_threshold ? 0 : delivery.fee
})
const amountToFreeDelivery = computed(() => {
  const threshold = shop.value?.delivery?.free_threshold

  return deliveryMethod.value === 'delivery' && threshold && shippingFee.value > 0 ? threshold - (subtotal.value - discount.value) : 0
})
const estimatedTotal = computed(() => Math.max(0, subtotal.value - discount.value) + shippingFee.value)
const trackingLink = computed(() => (order.value
  ? { path: `/boutique/${slug}/suivi`, query: { commande: order.value.order_number, email: order.value.customer.email } }
  : `/boutique/${slug}/suivi`))

watch(paymentOptions, (methods) => {
  if (!methods.some(method => method.value === paymentMethod.value)) {
    paymentMethod.value = methods[0]?.value ?? 'cash_on_delivery'
  }
}, { immediate: true })

/** Any cart change invalidates the computed discount: the customer must re-apply the code. */
watch(items, () => {
  if (appliedCoupon.value) {
    appliedCoupon.value = null
    couponError.value = 'Votre panier a changé : appliquez à nouveau votre code promo.'
  }
}, { deep: true })

watch(couponCode, (value) => {
  if (appliedCoupon.value && value.trim().toUpperCase() !== appliedCoupon.value.code.toUpperCase()) {
    appliedCoupon.value = null
  }
})

async function applyCoupon(): Promise<void> {
  const code = couponCode.value.trim()
  couponError.value = ''
  errors.value = { ...errors.value, coupon_code: '' }

  if (!code) {
    couponError.value = 'Saisissez un code promo.'

    return
  }

  checkingCoupon.value = true

  try {
    const { data } = await api<Resource<CouponCheck>>(`/storefront/${slug}/coupons/check`, {
      method: 'POST',
      body: { code, subtotal: subtotal.value },
    })
    appliedCoupon.value = data
    couponCode.value = data.code
  }
  catch (error) {
    appliedCoupon.value = null
    couponError.value = validationErrors(error).code ?? errorMessage(error, 'Ce code promo n’est pas valable.')
  }
  finally {
    checkingCoupon.value = false
  }
}

function removeCoupon(): void {
  appliedCoupon.value = null
  couponCode.value = ''
  couponError.value = ''
}

const itemErrors = computed(() => Object.entries(errors.value).filter(([field]) => field.startsWith('items')).map(([, message]) => message))

async function submit(): Promise<void> {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const { data, payment_url: paymentUrl } = await api<Resource<Order> & { payment_url?: string }>(`/storefront/${slug}/checkout`, {
      method: 'POST',
      body: {
        customer: { ...customer },
        items: items.value.map(item => ({ product_id: item.product_id, quantity: item.quantity })),
        coupon_code: appliedCoupon.value?.code ?? (couponCode.value.trim() || undefined),
        payment_method: paymentMethod.value,
        delivery_method: deliveryMethod.value,
        delivery_address: deliveryMethod.value === 'delivery' ? address.street : undefined,
        delivery_city: deliveryMethod.value === 'delivery' ? address.city : undefined,
      },
    })
    appliedCoupon.value = null

    if (data.payment_method === 'flutterwave') {
      if (!paymentUrl) {
        generalError.value = 'Le lien de paiement sécurisé n’a pas été reçu. Veuillez contacter la boutique avant de réessayer.'
        toast.error(generalError.value)

        return
      }

      clear()
      window.location.assign(paymentUrl)

      return
    }

    order.value = data
    clear()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  catch (error) {
    errors.value = validationErrors(error)

    if (!Object.keys(errors.value).length) {
      generalError.value = errorMessage(error, 'La commande n’a pas pu être validée.')
      toast.error(generalError.value)
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="container py-4" style="max-width: 1080px;">
    <section v-if="order" class="card p-4 p-md-5 text-center mx-auto" style="max-width: 640px;" aria-labelledby="confirmation-title">
      <span class="icon-tile mx-auto mb-3 text-success" style="width: 4rem; height: 4rem; font-size: 2rem;" aria-hidden="true"><i class="bi bi-check2-circle" /></span>
      <h1 id="confirmation-title" class="h3 fw-bold">Merci pour votre commande !</h1>
      <p class="text-muted">Un récapitulatif a été envoyé à {{ order.customer.email }}.</p>
      <div class="bg-body-tertiary rounded-3 p-3 my-3">
        <p class="small text-muted mb-1">Numéro de commande</p>
        <p class="fs-4 fw-bold mb-0">#{{ order.order_number }}</p>
      </div>
      <dl class="row text-start small mb-4">
        <dt class="col-6">Total</dt>
        <dd class="col-6 text-end fw-bold"><PriceTag :amount="order.total" :currency="order.currency" /></dd>
        <dt class="col-6">Paiement</dt>
        <dd class="col-6 text-end">{{ paymentMethodLabel(order.payment_method) }}</dd>
        <dt class="col-6">{{ order.delivery?.method === 'pickup' ? 'Retrait' : 'Livraison' }}</dt>
        <dd class="col-6 text-end">
          <template v-if="order.delivery?.method === 'pickup'">{{ shop?.delivery?.pickup_address ?? 'En boutique' }}</template>
          <template v-else>{{ [order.delivery?.address, order.delivery?.city].filter(Boolean).join(', ') || 'À domicile' }}</template>
        </dd>
      </dl>
      <p v-if="order.payment_method === 'cash_on_delivery'" class="small">
        Préparez le montant exact : vous réglerez {{ order.delivery?.method === 'pickup' ? 'au retrait de votre commande' : 'à la réception de votre colis' }}.
      </p>
      <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
        <NuxtLink :to="trackingLink" class="btn btn-outline-primary btn-lg">
          <i class="bi bi-truck me-1" aria-hidden="true" />Suivre ma commande
        </NuxtLink>
        <NuxtLink :to="`/boutique/${slug}`" class="btn btn-primary btn-lg">Continuer mes achats</NuxtLink>
      </div>
    </section>

    <template v-else>
      <h1 class="h3 fw-bold mb-4">Finaliser ma commande</h1>
      <ClientOnly>
        <AppState v-if="!items.length" icon="bi-bag" title="Votre panier est vide" text="Ajoutez des produits avant de passer commande.">
          <NuxtLink :to="`/boutique/${slug}`" class="btn btn-primary">Voir les produits</NuxtLink>
        </AppState>
        <form v-else class="row g-4" novalidate @submit.prevent="submit">
          <div class="col-lg-7 d-grid gap-4">
            <div v-if="generalError" class="alert alert-danger mb-0" role="alert">{{ generalError }}</div>
            <div v-if="itemErrors.length" class="alert alert-warning mb-0" role="alert">
              <ul class="mb-0 ps-3"><li v-for="message in itemErrors" :key="message">{{ message }}</li></ul>
            </div>
            <fieldset class="card p-4">
              <legend class="h5 fw-bold float-none mb-3">Vos coordonnées</legend>
              <div class="row g-3">
                <div v-for="field in CUSTOMER_FIELDS" :key="field.key" :class="field.col">
                  <label :for="`customer-${field.key}`" class="form-label">{{ field.label }}</label>
                  <input
                    :id="`customer-${field.key}`"
                    v-model="customer[field.key]"
                    :type="field.type"
                    class="form-control"
                    :class="{ 'is-invalid': errors[`customer.${field.key}`] }"
                    :autocomplete="field.autocomplete"
                    required
                    :aria-describedby="`customer-${field.key}-error`"
                  >
                  <FieldError :id="`customer-${field.key}-error`" :message="errors[`customer.${field.key}`]" />
                </div>
              </div>
            </fieldset>
            <fieldset class="card p-4">
              <legend class="h5 fw-bold float-none mb-3">Livraison</legend>
              <div class="d-grid gap-2 mb-3">
                <div v-if="shop?.delivery?.methods.includes('delivery')">
                  <input id="delivery-home" v-model="deliveryMethod" type="radio" class="btn-check" name="delivery_method" value="delivery">
                  <label for="delivery-home" class="payment-option d-flex align-items-center gap-3 p-3 w-100">
                    <span class="icon-tile" aria-hidden="true"><i class="bi bi-truck" /></span>
                    <span class="flex-grow-1">
                      <span class="d-block fw-bold">Livraison à domicile</span>
                      <span class="d-block small text-muted">
                        {{ [shop.delivery.zone, shop.delivery.delay].filter(Boolean).join(' · ') || 'Livrée à l’adresse de votre choix' }}
                      </span>
                    </span>
                    <span class="fw-semibold text-nowrap">{{ shop.delivery.fee > 0 ? formatPrice(shop.delivery.fee, currency) : 'Offerte' }}</span>
                  </label>
                </div>
                <div v-if="shop?.delivery?.methods.includes('pickup')">
                  <input id="delivery-pickup" v-model="deliveryMethod" type="radio" class="btn-check" name="delivery_method" value="pickup">
                  <label for="delivery-pickup" class="payment-option d-flex align-items-center gap-3 p-3 w-100">
                    <span class="icon-tile" aria-hidden="true"><i class="bi bi-shop" /></span>
                    <span class="flex-grow-1">
                      <span class="d-block fw-bold">Retrait en boutique</span>
                      <span class="d-block small text-muted">{{ shop.delivery.pickup_address }}</span>
                    </span>
                    <span class="fw-semibold text-nowrap">Gratuit</span>
                  </label>
                </div>
              </div>
              <FieldError id="delivery-method-error" :message="errors.delivery_method" />
              <div v-if="deliveryMethod === 'delivery'" class="row g-3">
                <div class="col-md-8">
                  <label for="delivery-address" class="form-label">Adresse de livraison</label>
                  <input id="delivery-address" v-model="address.street" type="text" class="form-control" :class="{ 'is-invalid': errors.delivery_address }" autocomplete="street-address" placeholder="Quartier, rue, repère…" required aria-describedby="delivery-address-error">
                  <FieldError id="delivery-address-error" :message="errors.delivery_address" />
                </div>
                <div class="col-md-4">
                  <label for="delivery-city" class="form-label">Commune / ville</label>
                  <input id="delivery-city" v-model="address.city" type="text" class="form-control" :class="{ 'is-invalid': errors.delivery_city }" autocomplete="address-level2" required aria-describedby="delivery-city-error">
                  <FieldError id="delivery-city-error" :message="errors.delivery_city" />
                </div>
              </div>
            </fieldset>
            <fieldset class="card p-4">
              <legend class="h5 fw-bold float-none mb-3">Mode de paiement</legend>
              <p v-if="!paymentOptions.length" class="small text-muted mb-0">Aucun mode de paiement n’est actuellement disponible pour cette boutique.</p>
              <div class="d-grid gap-2">
                <div v-for="method in paymentOptions" :key="method.value">
                  <input :id="`payment-${method.value}`" v-model="paymentMethod" type="radio" class="btn-check" name="payment_method" :value="method.value">
                  <label :for="`payment-${method.value}`" class="payment-option d-flex align-items-center gap-3 p-3 w-100">
                    <span class="icon-tile" aria-hidden="true"><i class="bi" :class="method.icon" /></span>
                    <span>
                      <span class="d-block fw-bold">{{ method.label }}</span>
                      <span class="d-block small text-muted">{{ method.description }}</span>
                    </span>
                  </label>
                </div>
              </div>
              <FieldError id="payment-error" :message="errors.payment_method" />
            </fieldset>
          </div>
          <div class="col-lg-5">
            <div class="card p-4 position-sticky" style="top: 6rem;">
              <h2 class="h5 fw-bold mb-3">Récapitulatif</h2>
              <ul class="list-unstyled d-grid gap-2 small mb-3">
                <li v-for="item in items" :key="item.product_id" class="d-flex justify-content-between gap-2">
                  <span>{{ item.quantity }} × {{ item.name }}</span>
                  <PriceTag :amount="item.price * item.quantity" :currency="item.currency" />
                </li>
              </ul>
              <label for="coupon-code" class="form-label small">Code promo</label>
              <div class="input-group mb-1" :class="{ 'has-validation': couponError || errors.coupon_code }">
                <input
                  id="coupon-code"
                  v-model="couponCode"
                  type="text"
                  class="form-control text-uppercase"
                  :class="{ 'is-invalid': couponError || errors.coupon_code, 'is-valid': appliedCoupon }"
                  autocomplete="off"
                  :readonly="Boolean(appliedCoupon)"
                  aria-describedby="coupon-code-status coupon-code-error"
                  @keydown.enter.prevent="applyCoupon"
                >
                <button v-if="appliedCoupon" type="button" class="btn btn-outline-secondary" @click="removeCoupon">Retirer</button>
                <button v-else type="button" class="btn btn-outline-primary" :disabled="checkingCoupon" @click="applyCoupon">
                  <span v-if="checkingCoupon" class="spinner-border spinner-border-sm" aria-hidden="true" />
                  <template v-else>Appliquer</template>
                  <span v-if="checkingCoupon" class="visually-hidden">Vérification du code…</span>
                </button>
              </div>
              <div id="coupon-code-status" class="small text-success" aria-live="polite">
                <template v-if="appliedCoupon">
                  <i class="bi bi-check-circle me-1" aria-hidden="true" />Code {{ appliedCoupon.code }} appliqué ({{ formatCouponValue(appliedCoupon, currency) }} de réduction).
                </template>
              </div>
              <FieldError id="coupon-code-error" :message="couponError || errors.coupon_code" />
              <dl class="small border-top pt-3 mt-3 mb-0">
                <div class="d-flex justify-content-between">
                  <dt class="fw-normal">Sous-total</dt>
                  <dd class="mb-1"><PriceTag :amount="subtotal" :currency="currency" /></dd>
                </div>
                <div v-if="appliedCoupon" class="d-flex justify-content-between text-success">
                  <dt class="fw-normal">Remise ({{ appliedCoupon.code }})</dt>
                  <dd class="mb-1">−{{ formatPrice(discount, currency) }}</dd>
                </div>
                <div class="d-flex justify-content-between">
                  <dt class="fw-normal">{{ deliveryMethod === 'pickup' ? 'Retrait en boutique' : 'Livraison' }}</dt>
                  <dd class="mb-1">{{ shippingFee > 0 ? formatPrice(shippingFee, currency) : 'Gratuit' }}</dd>
                </div>
              </dl>
              <div class="d-flex justify-content-between fw-bold fs-5 pt-2">
                <span>Total</span>
                <PriceTag :amount="estimatedTotal" :currency="currency" />
              </div>
              <p v-if="amountToFreeDelivery > 0" class="small text-success">
                <i class="bi bi-gift me-1" aria-hidden="true" />Plus que {{ formatPrice(amountToFreeDelivery, currency) }} pour la livraison offerte.
              </p>
              <p v-else class="small text-muted">Montant définitif confirmé à la validation.</p>
              <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="submitting || !paymentOptions.length">
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                Valider ma commande
              </button>
            </div>
          </div>
        </form>
        <template #fallback>
          <AppState loading text="Chargement de votre panier…" />
        </template>
      </ClientOnly>
    </template>
  </div>
</template>

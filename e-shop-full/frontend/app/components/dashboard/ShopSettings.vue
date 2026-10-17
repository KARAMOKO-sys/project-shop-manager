<script setup lang="ts">
import type { Resource, Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const emit = defineEmits<{ updated: [shop: Shop] }>()

const api = useApi()
const toast = useToast()

const form = reactive({
  name: props.shop.name,
  tagline: props.shop.tagline ?? '',
  description: props.shop.description ?? '',
  currency: props.shop.currency || 'XOF',
})
const paymentForm = reactive({
  cash_on_delivery_enabled: props.shop.cash_on_delivery_enabled,
  flutterwave_enabled: props.shop.flutterwave_enabled,
  flutterwave_secret_key: '',
  flutterwave_webhook_secret_hash: '',
})
const errors = ref<Record<string, string>>({})
const paymentErrors = ref<Record<string, string>>({})
const saving = ref(false)
const savingPayments = ref(false)
const deleting = ref(false)
const logoUrl = ref(props.shop.logo_url ?? '')
const savingLogo = ref(false)
const runtimeConfig = useRuntimeConfig()
const flutterwaveWebhookUrl = computed(() => `${String(runtimeConfig.public.apiBase).replace(/\/+$/, '')}/payments/flutterwave/webhook`)

async function saveLogo(url: string | null): Promise<void> {
  savingLogo.value = true

  try {
    const { data } = await api<Resource<Shop>>(`/shops/${props.shop.id}`, { method: 'PUT', body: { logo_url: url } })
    logoUrl.value = data.logo_url ?? ''
    emit('updated', data)
    toast.success(url ? 'Logo mis à jour.' : 'Logo retiré.')
  }
  catch (error) {
    toast.error(validationErrors(error).logo_url ?? errorMessage(error, 'Le logo n’a pas pu être enregistré.'))
  }
  finally {
    savingLogo.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    const { data } = await api<Resource<Shop>>(`/shops/${props.shop.id}`, { method: 'PUT', body: { ...form } })
    emit('updated', data)
    toast.success('Paramètres enregistrés.')
  }
  catch (error) {
    errors.value = validationErrors(error)

    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error))
    }
  }
  finally {
    saving.value = false
  }
}

async function savePayments(): Promise<void> {
  savingPayments.value = true
  paymentErrors.value = {}

  const body: Record<string, string | boolean> = {
    cash_on_delivery_enabled: paymentForm.cash_on_delivery_enabled,
    flutterwave_enabled: paymentForm.flutterwave_enabled,
  }

  if (paymentForm.flutterwave_secret_key.trim()) {
    body.flutterwave_secret_key = paymentForm.flutterwave_secret_key.trim()
  }

  if (paymentForm.flutterwave_webhook_secret_hash.trim()) {
    body.flutterwave_webhook_secret_hash = paymentForm.flutterwave_webhook_secret_hash.trim()
  }

  try {
    const { data } = await api<Resource<Shop>>(`/shops/${props.shop.id}`, { method: 'PUT', body })
    paymentForm.flutterwave_secret_key = ''
    paymentForm.flutterwave_webhook_secret_hash = ''
    emit('updated', data)
    toast.success('Modes de paiement enregistrés.')
  }
  catch (error) {
    paymentErrors.value = validationErrors(error)

    if (!Object.keys(paymentErrors.value).length) {
      toast.error(errorMessage(error, 'Les modes de paiement n’ont pas pu être enregistrés.'))
    }
  }
  finally {
    savingPayments.value = false
  }
}

async function destroy(): Promise<void> {
  if (!window.confirm(`Supprimer définitivement la boutique « ${props.shop.name} » et tous ses produits ?`)) {
    return
  }

  deleting.value = true

  try {
    await api(`/shops/${props.shop.id}`, { method: 'DELETE' })
    toast.success('Boutique supprimée.')
    await navigateTo('/dashboard')
  }
  catch (error) {
    toast.error(errorMessage(error, 'La suppression a échoué.'))
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="d-grid gap-4" style="max-width: 760px;">
    <section class="card p-4" aria-labelledby="settings-logo-title">
      <h2 id="settings-logo-title" class="h5 fw-bold mb-3">Logo</h2>
      <div class="d-flex flex-column flex-sm-row gap-4 align-items-sm-center">
        <span class="settings-logo-preview" :style="{ background: shop.theme?.primary, color: contrastText(shop.theme?.primary ?? '#0F3D2E') }">
          <img v-if="logoUrl" :src="logoUrl" :alt="`Logo de ${shop.name}`">
          <span v-else aria-hidden="true">{{ shop.name.charAt(0).toUpperCase() }}</span>
        </span>
        <div class="flex-grow-1 d-grid gap-2">
          <DashboardImageUploadInput id="settings-logo-file" :shop-id="shop.id" label="Téléverser un logo" help="Format carré conseillé. JPEG, PNG ou WebP — 4 Mo maximum." @uploaded="saveLogo" />
          <div class="d-flex align-items-center gap-2">
            <span v-if="savingLogo" class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Enregistrement du logo…</span></span>
            <button v-if="logoUrl" type="button" class="btn btn-sm btn-light text-danger" :disabled="savingLogo" @click="saveLogo(null)">
              <i class="bi bi-x-lg me-1" aria-hidden="true" />Retirer le logo
            </button>
          </div>
        </div>
      </div>
    </section>

    <form class="card p-4" novalidate @submit.prevent="save">
      <h2 class="h5 fw-bold mb-3">Informations générales</h2>
      <div class="row g-3">
        <div class="col-md-7">
          <label for="settings-name" class="form-label">Nom de la boutique</label>
          <input id="settings-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" required aria-describedby="settings-name-error">
          <FieldError id="settings-name-error" :message="errors.name" />
        </div>
        <div class="col-md-5">
          <label for="settings-currency" class="form-label">Devise</label>
          <select id="settings-currency" v-model="form.currency" class="form-select" :class="{ 'is-invalid': errors.currency }" aria-describedby="settings-currency-error">
            <option v-for="currency in CURRENCIES" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
          </select>
          <FieldError id="settings-currency-error" :message="errors.currency" />
        </div>
        <div class="col-12">
          <label for="settings-tagline" class="form-label">Slogan</label>
          <input id="settings-tagline" v-model="form.tagline" type="text" class="form-control" :class="{ 'is-invalid': errors.tagline }" aria-describedby="settings-tagline-error">
          <FieldError id="settings-tagline-error" :message="errors.tagline" />
        </div>
        <div class="col-12">
          <label for="settings-description" class="form-label">Description</label>
          <textarea id="settings-description" v-model="form.description" rows="4" class="form-control" :class="{ 'is-invalid': errors.description }" aria-describedby="settings-description-error" />
          <FieldError id="settings-description-error" :message="errors.description" />
        </div>
      </div>
      <div class="mt-4">
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          Enregistrer
        </button>
      </div>
    </form>

    <form class="card p-4" novalidate @submit.prevent="savePayments">
      <h2 class="h5 fw-bold mb-2">Paiements</h2>
      <p class="text-muted">Les clés sont chiffrées côté serveur et ne sont jamais renvoyées à l’interface.</p>

      <div class="form-check mb-3">
        <input id="settings-cash-payment" v-model="paymentForm.cash_on_delivery_enabled" class="form-check-input" type="checkbox">
        <label for="settings-cash-payment" class="form-check-label">Accepter le paiement à la livraison</label>
      </div>

      <div class="form-check mb-3">
        <input id="settings-flutterwave-enabled" v-model="paymentForm.flutterwave_enabled" class="form-check-input" type="checkbox">
        <label for="settings-flutterwave-enabled" class="form-check-label">Accepter les paiements en ligne via Flutterwave</label>
      </div>

      <div v-if="props.shop.flutterwave_configured" class="alert alert-success py-2" role="status">
        <i class="bi bi-shield-check me-1" aria-hidden="true" />Compte Flutterwave configuré. Les valeurs secrètes ne sont pas affichées.
      </div>

      <div class="row g-3">
        <div class="col-12">
          <label for="settings-flutterwave-key" class="form-label">Clé secrète Flutterwave</label>
          <input
            id="settings-flutterwave-key"
            v-model="paymentForm.flutterwave_secret_key"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': paymentErrors.flutterwave_secret_key }"
            autocomplete="new-password"
            :required="paymentForm.flutterwave_enabled && !props.shop.flutterwave_configured"
            aria-describedby="settings-flutterwave-key-help settings-flutterwave-key-error"
          >
          <div id="settings-flutterwave-key-help" class="form-text">Saisissez une clé de test pour valider le parcours avant d’activer les paiements en production.</div>
          <FieldError id="settings-flutterwave-key-error" :message="paymentErrors.flutterwave_secret_key" />
        </div>
        <div class="col-12">
          <label for="settings-flutterwave-webhook" class="form-label">Secret hash du webhook Flutterwave</label>
          <input
            id="settings-flutterwave-webhook"
            v-model="paymentForm.flutterwave_webhook_secret_hash"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': paymentErrors.flutterwave_webhook_secret_hash }"
            autocomplete="new-password"
            :required="paymentForm.flutterwave_enabled && !props.shop.flutterwave_configured"
            aria-describedby="settings-flutterwave-webhook-help settings-flutterwave-webhook-error"
          >
          <div id="settings-flutterwave-webhook-help" class="form-text">
            URL du webhook à enregistrer dans Flutterwave :
            <code>{{ flutterwaveWebhookUrl }}</code>
          </div>
          <FieldError id="settings-flutterwave-webhook-error" :message="paymentErrors.flutterwave_webhook_secret_hash" />
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary" :disabled="savingPayments">
          <span v-if="savingPayments" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          Enregistrer les paiements
        </button>
      </div>
    </form>

    <div class="card p-4 border-danger-subtle">
      <h2 class="h5 fw-bold text-danger">Zone de danger</h2>
      <p class="text-muted">La suppression est définitive : produits, commandes et paramètres seront perdus.</p>
      <div>
        <button type="button" class="btn btn-outline-danger" :disabled="deleting" @click="destroy">
          <i class="bi bi-trash me-1" aria-hidden="true" />Supprimer la boutique
        </button>
      </div>
    </div>
  </div>
</template>

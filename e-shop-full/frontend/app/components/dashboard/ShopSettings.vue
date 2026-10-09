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
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const deleting = ref(false)
const logoUrl = ref(props.shop.logo_url ?? '')
const savingLogo = ref(false)

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

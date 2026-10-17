<script setup lang="ts">
import type { PlatformSettingDefinition, PlatformSettingsPayload, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] })
useSeoMeta({ title: 'Paramètres de la plateforme', robots: 'noindex' })

type SettingValue = string | number | boolean | null

const api = useApi()
const toast = useToast()
const platform = usePlatform()

const GROUP_ICONS: Record<string, string> = {
  platform: 'bi-building',
  features: 'bi-toggles',
  payments: 'bi-wallet2',
  limits: 'bi-speedometer2',
  ai: 'bi-stars',
  moderation: 'bi-shield-check',
}

const form = reactive<Record<string, SettingValue>>({})
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const { data: settings, status, error, refresh } = useAsyncData(
  'admin-settings',
  () => api<Resource<PlatformSettingsPayload>>('/admin/settings').then(response => response.data),
  { server: false },
)

watch(settings, (payload) => {
  if (payload) {
    Object.assign(form, payload.values)
  }
}, { immediate: true })

/** Definitions grouped in the order the API gives the groups. */
const groups = computed(() => {
  if (!settings.value) {
    return []
  }

  const { groups: labels, definitions } = settings.value

  return Object.entries(labels).map(([key, label]) => ({
    key,
    label,
    settings: Object.entries(definitions)
      .filter(([, definition]) => definition.group === key)
      .map(([name, definition]) => ({ name, definition })),
  }))
})

const changedKeys = computed(() => Object.keys(form).filter(key => form[key] !== settings.value?.values[key]))

function fieldId(name: string): string {
  return `setting-${name.replace('.', '-')}`
}

function inputType(definition: PlatformSettingDefinition): string {
  return definition.type === 'email' ? 'email' : definition.type === 'integer' ? 'number' : 'text'
}

/** "features.registration" → { features: { registration: … } }, as the API expects. */
function nestedChanges(): Record<string, Record<string, SettingValue>> {
  return changedKeys.value.reduce<Record<string, Record<string, SettingValue>>>((body, key) => {
    const [group, name] = key.split('.') as [string, string]
    body[group] = { ...body[group], [name]: form[key] ?? null }

    return body
  }, {})
}

async function save(): Promise<void> {
  if (!changedKeys.value.length) {
    toast.show('Aucune modification à enregistrer.', 'info')

    return
  }

  saving.value = true
  errors.value = {}

  try {
    const { data } = await api<Resource<PlatformSettingsPayload>>('/admin/settings', { method: 'PUT', body: nestedChanges() })
    settings.value = data
    await platform.load()
    toast.success('Paramètres enregistrés. Ils s’appliquent immédiatement à toute la plateforme.')
  }
  catch (requestError) {
    errors.value = validationErrors(requestError)
    toast.error(errorMessage(requestError, 'Les paramètres n’ont pas pu être enregistrés.'))
  }
  finally {
    saving.value = false
  }
}

function reset(): void {
  if (settings.value) {
    Object.assign(form, settings.value.values)
  }

  errors.value = {}
}
</script>

<template>
  <div style="max-width: 920px;">
    <div class="mb-4">
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-sliders me-2 text-primary" aria-hidden="true" />Paramètres de la plateforme</h1>
      <p class="text-muted mb-0">Configurez la plateforme sans toucher au code. Les changements s’appliquent immédiatement à toutes les boutiques.</p>
    </div>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des paramètres…" />
    <AppState v-else-if="error" variant="error" title="Paramètres indisponibles" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <form v-else novalidate class="d-grid gap-4" @submit.prevent="save">
      <section v-for="group in groups" :key="group.key" class="card p-4" :aria-labelledby="`group-${group.key}`">
        <h2 :id="`group-${group.key}`" class="h5 fw-bold mb-3">
          <i class="bi me-2 text-primary" :class="GROUP_ICONS[group.key] ?? 'bi-gear'" aria-hidden="true" />{{ group.label }}
        </h2>
        <div class="row g-3">
          <div
            v-for="{ name, definition } in group.settings"
            :key="name"
            :class="definition.type === 'boolean' || definition.type === 'text' ? 'col-12' : 'col-md-6'"
          >
            <div v-if="definition.type === 'boolean'" class="form-check form-switch">
              <input
                :id="fieldId(name)"
                v-model="form[name]"
                class="form-check-input"
                type="checkbox"
                role="switch"
                :aria-describedby="definition.help ? `${fieldId(name)}-help` : undefined"
              >
              <label :for="fieldId(name)" class="form-check-label fw-semibold">{{ definition.label }}</label>
              <p v-if="definition.help" :id="`${fieldId(name)}-help`" class="form-text mt-0 mb-0">{{ definition.help }}</p>
            </div>

            <template v-else>
              <label :for="fieldId(name)" class="form-label fw-semibold">{{ definition.label }}</label>
              <select
                v-if="definition.type === 'select'"
                :id="fieldId(name)"
                v-model="form[name]"
                class="form-select"
                :class="{ 'is-invalid': errors[name] }"
                :aria-describedby="`${fieldId(name)}-help ${fieldId(name)}-error`"
              >
                <option v-for="(label, value) in definition.options" :key="value" :value="value">{{ label }}</option>
              </select>
              <textarea
                v-else-if="definition.type === 'text'"
                :id="fieldId(name)"
                v-model="form[name]"
                rows="2"
                class="form-control"
                :class="{ 'is-invalid': errors[name] }"
                :maxlength="definition.max"
                :aria-describedby="`${fieldId(name)}-help ${fieldId(name)}-error`"
              />
              <input
                v-else-if="definition.type === 'integer'"
                :id="fieldId(name)"
                v-model.number="form[name]"
                type="number"
                class="form-control"
                :class="{ 'is-invalid': errors[name] }"
                :min="definition.min"
                :max="definition.max"
                :aria-describedby="`${fieldId(name)}-help ${fieldId(name)}-error`"
              >
              <input
                v-else
                :id="fieldId(name)"
                v-model="form[name]"
                :type="inputType(definition)"
                class="form-control"
                :class="{ 'is-invalid': errors[name] }"
                :maxlength="definition.max"
                :aria-describedby="`${fieldId(name)}-help ${fieldId(name)}-error`"
              >
              <p v-if="definition.help" :id="`${fieldId(name)}-help`" class="form-text mb-0">{{ definition.help }}</p>
              <FieldError :id="`${fieldId(name)}-error`" :message="errors[name]" />
            </template>
          </div>
        </div>
      </section>

      <div class="d-flex justify-content-end gap-2 sticky-bottom bg-body py-3">
        <span v-if="changedKeys.length" class="small text-muted align-self-center me-auto">
          {{ changedKeys.length }} modification(s) non enregistrée(s)
        </span>
        <button type="button" class="btn btn-light" :disabled="saving || !changedKeys.length" @click="reset">Annuler</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
          Enregistrer
        </button>
      </div>
    </form>
  </div>
</template>

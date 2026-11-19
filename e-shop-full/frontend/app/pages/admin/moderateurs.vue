<script setup lang="ts">
import type { Moderator, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] })
useSeoMeta({ title: 'Modérateurs', robots: 'noindex' })

interface ModeratorWithPassword extends Resource<Moderator> {
  meta: { password: string }
}

const api = useApi()
const toast = useToast()

const form = reactive({ name: '', email: '', password: '' })
const errors = ref<Record<string, string>>({})
const creating = ref(false)
const busy = ref<number | null>(null)
/** Shown once after a creation or a reset: the password is never sent again by the API. */
const credentials = ref<{ name: string, email: string, password: string } | null>(null)

const { data: moderators, status, error, refresh } = useAsyncData(
  'admin-moderators',
  () => api<Resource<Moderator[]>>('/admin/moderators').then(response => response.data),
  { server: false },
)

function replace(moderator: Moderator): void {
  moderators.value = (moderators.value ?? []).map(item => (item.id === moderator.id ? moderator : item))
}

async function create(): Promise<void> {
  creating.value = true
  errors.value = {}

  try {
    const response = await api<ModeratorWithPassword>('/admin/moderators', {
      method: 'POST',
      body: { name: form.name, email: form.email, password: form.password || undefined },
    })
    moderators.value = [response.data, ...(moderators.value ?? [])]
    credentials.value = { name: response.data.name, email: response.data.email, password: response.meta.password }
    Object.assign(form, { name: '', email: '', password: '' })
    toast.success(`Compte modérateur créé pour ${response.data.name}.`)
  }
  catch (requestError) {
    errors.value = validationErrors(requestError)
    toast.error(errorMessage(requestError, 'Le compte n’a pas pu être créé.'))
  }
  finally {
    creating.value = false
  }
}

async function toggleSuspension(moderator: Moderator): Promise<void> {
  busy.value = moderator.id

  try {
    const { data } = await api<Resource<Moderator>>(`/admin/moderators/${moderator.id}`, {
      method: 'PATCH',
      body: { suspended: !moderator.is_suspended },
    })
    replace(data)
    toast.success(data.is_suspended ? `${data.name} est suspendu et a été déconnecté.` : `${data.name} est réactivé.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    busy.value = null
  }
}

async function resetPassword(moderator: Moderator): Promise<void> {
  if (!confirm(`Générer un nouveau mot de passe pour ${moderator.name} ? L’ancien ne fonctionnera plus.`)) {
    return
  }

  busy.value = moderator.id

  try {
    const response = await api<ModeratorWithPassword>(`/admin/moderators/${moderator.id}/reset-password`, { method: 'POST' })
    credentials.value = { name: response.data.name, email: response.data.email, password: response.meta.password }
    toast.success('Nouveau mot de passe généré.')
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    busy.value = null
  }
}

async function destroy(moderator: Moderator): Promise<void> {
  if (!confirm(`Supprimer définitivement le compte de ${moderator.name} ?`)) {
    return
  }

  busy.value = moderator.id

  try {
    await api(`/admin/moderators/${moderator.id}`, { method: 'DELETE' })
    moderators.value = (moderators.value ?? []).filter(item => item.id !== moderator.id)
    toast.success(`Compte de ${moderator.name} supprimé.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    busy.value = null
  }
}

async function copyCredentials(): Promise<void> {
  if (!credentials.value) {
    return
  }

  const { email, password } = credentials.value

  try {
    await navigator.clipboard.writeText(`E-mail : ${email}\nMot de passe : ${password}`)
    toast.success('Identifiants copiés.')
  }
  catch {
    toast.error('Copie impossible : sélectionnez le texte manuellement.')
  }
}
</script>

<template>
  <div style="max-width: 1100px;">
    <div class="mb-4">
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-people me-2 text-primary" aria-hidden="true" />Modérateurs</h1>
      <p class="text-muted mb-0">Les modérateurs examinent les produits signalés par l’agent IA. Ils n’ont pas accès aux paramètres de la plateforme.</p>
    </div>

    <div v-if="credentials" class="alert alert-success" role="status">
      <h2 class="h6 fw-bold"><i class="bi bi-key me-1" aria-hidden="true" />Identifiants de {{ credentials.name }}</h2>
      <p class="small mb-2">Transmettez-les maintenant : le mot de passe ne sera plus affiché ensuite.</p>
      <dl class="row small mb-2">
        <dt class="col-sm-3">E-mail</dt>
        <dd class="col-sm-9 text-break"><code>{{ credentials.email }}</code></dd>
        <dt class="col-sm-3">Mot de passe</dt>
        <dd class="col-sm-9 text-break"><code>{{ credentials.password }}</code></dd>
      </dl>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-success" @click="copyCredentials"><i class="bi bi-clipboard me-1" aria-hidden="true" />Copier</button>
        <button type="button" class="btn btn-sm btn-light" @click="credentials = null">J’ai noté, masquer</button>
      </div>
    </div>

    <form class="card p-4 mb-4" novalidate aria-labelledby="new-moderator-title" @submit.prevent="create">
      <h2 id="new-moderator-title" class="h5 fw-bold mb-3">Nouveau modérateur</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <label for="moderator-name" class="form-label">Nom complet</label>
          <input id="moderator-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" autocomplete="off" required aria-describedby="moderator-name-error">
          <FieldError id="moderator-name-error" :message="errors.name" />
        </div>
        <div class="col-md-4">
          <label for="moderator-email" class="form-label">E-mail</label>
          <input id="moderator-email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" autocomplete="off" required aria-describedby="moderator-email-error">
          <FieldError id="moderator-email-error" :message="errors.email" />
        </div>
        <div class="col-md-4">
          <label for="moderator-password" class="form-label">Mot de passe <span class="text-muted fw-normal">(optionnel)</span></label>
          <input id="moderator-password" v-model="form.password" type="text" class="form-control" :class="{ 'is-invalid': errors.password }" autocomplete="new-password" aria-describedby="moderator-password-help moderator-password-error">
          <p id="moderator-password-help" class="form-text mb-0">Laissez vide pour en générer un sûr automatiquement.</p>
          <FieldError id="moderator-password-error" :message="errors.password" />
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary" :disabled="creating">
          <span v-if="creating" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
          <i v-else class="bi bi-person-plus me-1" aria-hidden="true" />Créer le compte
        </button>
      </div>
    </form>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des modérateurs…" />
    <AppState v-else-if="error" variant="error" title="Liste indisponible" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <div v-else-if="!moderators?.length" class="card">
      <AppState icon="bi-people" title="Aucun modérateur" text="Créez un premier compte pour partager la file de modération." />
    </div>
    <div v-else class="card">
      <div class="table-responsive">
        <table class="table table-stack align-middle mb-0">
          <caption class="visually-hidden">Comptes modérateurs</caption>
          <thead class="table-light">
            <tr>
              <th scope="col">Modérateur</th>
              <th scope="col">Statut</th>
              <th scope="col">Décisions</th>
              <th scope="col">Créé</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="moderator in moderators" :key="moderator.id">
              <td>
                <p class="fw-semibold mb-0">{{ moderator.name }}</p>
                <p class="small text-muted mb-0 text-break">{{ moderator.email }}</p>
              </td>
              <td data-label="Statut">
                <span class="badge rounded-pill" :class="moderator.is_suspended ? 'text-bg-secondary' : 'text-bg-success'">
                  {{ moderator.is_suspended ? 'Suspendu' : 'Actif' }}
                </span>
              </td>
              <td data-label="Décisions">{{ formatNumber(moderator.decisions_count) }}</td>
              <td data-label="Créé" class="small">
                {{ formatShortDate(moderator.created_at) }}<span v-if="moderator.created_by" class="text-muted"> · par {{ moderator.created_by }}</span>
              </td>
              <td class="text-end text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-light"
                  :disabled="busy === moderator.id"
                  :title="moderator.is_suspended ? 'Réactiver' : 'Suspendre'"
                  :aria-label="moderator.is_suspended ? `Réactiver ${moderator.name}` : `Suspendre ${moderator.name}`"
                  @click="toggleSuspension(moderator)"
                >
                  <i class="bi" :class="moderator.is_suspended ? 'bi-play-circle' : 'bi-pause-circle'" aria-hidden="true" />
                </button>
                <button type="button" class="btn btn-sm btn-light ms-1" :disabled="busy === moderator.id" title="Nouveau mot de passe" :aria-label="`Nouveau mot de passe pour ${moderator.name}`" @click="resetPassword(moderator)">
                  <i class="bi bi-key" aria-hidden="true" />
                </button>
                <button type="button" class="btn btn-sm btn-light text-danger ms-1" :disabled="busy === moderator.id" title="Supprimer" :aria-label="`Supprimer ${moderator.name}`" @click="destroy(moderator)">
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

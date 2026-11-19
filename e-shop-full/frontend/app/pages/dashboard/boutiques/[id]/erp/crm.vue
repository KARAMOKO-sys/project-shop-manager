<script setup lang="ts">
import type { CrmLead, LeadSource, LeadStage, Pipeline, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'CRM' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const shopId = computed(() => String(route.params.id))
const { shop, loading, error, refresh, isInstalled, apiBase } = useErpShop(shopId, 'crm')

const currency = computed(() => shop.value?.currency ?? 'XOF')
const enabled = computed(() => !loading.value && isInstalled.value && !!shop.value)
const search = ref('')
const searchQuery = debouncedRef(search)

const { data: leads, status: leadStatus, error: leadError, refresh: refreshLeads } = useAsyncData(
  () => `erp-leads-${shopId.value}`,
  () => (enabled.value
    ? api<Resource<CrmLead[]>>(`${apiBase.value}/leads${queryString({ search: searchQuery.value })}`).then(response => response.data)
    : Promise.resolve(null)),
  { server: false, watch: [enabled, searchQuery] },
)

const { data: pipeline, refresh: refreshPipeline } = useAsyncData(
  () => `erp-pipeline-${shopId.value}`,
  () => (enabled.value ? api<{ data: Pipeline }>(`${apiBase.value}/leads/pipeline`).then(response => response.data) : Promise.resolve(null)),
  { server: false, watch: [enabled] },
)

const columns = computed(() => LEAD_STAGES.map(stage => ({
  ...stage,
  leads: (leads.value ?? []).filter(lead => lead.stage === stage.value),
  totals: pipeline.value?.stages.find(item => item.stage === stage.value),
})))

async function reload(): Promise<void> {
  await Promise.all([refreshLeads(), refreshPipeline()])
}

/* Stage moves */
const lostModal = ref(false)
const lostReason = ref('')
const lostLead = ref<CrmLead | null>(null)
const moving = ref<number | null>(null)

function stageIndex(stage: LeadStage): number {
  return LEAD_STAGES.findIndex(item => item.value === stage)
}

async function moveTo(lead: CrmLead, stage: LeadStage, reason?: string): Promise<void> {
  if (lead.stage === stage) {
    return
  }

  if (stage === 'lost' && !reason) {
    lostLead.value = lead
    lostReason.value = ''
    lostModal.value = true

    return
  }

  moving.value = lead.id

  try {
    await api(`${apiBase.value}/leads/${lead.id}/stage`, { method: 'POST', body: { stage, lost_reason: reason ?? null } })
    toast.success(stage === 'won' ? `Bravo ! « ${lead.title} » est gagnée.` : `« ${lead.title} » déplacée.`)
    await reload()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
  finally {
    moving.value = null
  }
}

async function confirmLost(): Promise<void> {
  if (!lostLead.value || !lostReason.value.trim()) {
    return
  }

  lostModal.value = false
  await moveTo(lostLead.value, 'lost', lostReason.value.trim())
}

/* Drag & drop (mouse); buttons remain available for keyboard and touch. */
const dragged = ref<CrmLead | null>(null)
const dropTarget = ref<LeadStage | null>(null)

function onDrop(stage: LeadStage): void {
  const lead = dragged.value
  dragged.value = null
  dropTarget.value = null

  if (lead) {
    moveTo(lead, stage)
  }
}

/* Lead form */
const modal = ref(false)
const saving = ref(false)
const formErrors = ref<Record<string, string>>({})
const form = reactive({
  id: null as number | null,
  title: '',
  contact_name: '',
  email: '',
  phone: '',
  source: 'whatsapp' as LeadSource,
  expected_revenue: 0,
  probability: null as number | null,
  expected_close_date: '',
  notes: '',
  stage: 'new' as LeadStage,
})

function openForm(lead?: CrmLead, stage: LeadStage = 'new'): void {
  Object.assign(form, lead
    ? { id: lead.id, title: lead.title, contact_name: lead.contact_name, email: lead.email ?? '', phone: lead.phone ?? '', source: lead.source, expected_revenue: lead.expected_revenue, probability: lead.probability, expected_close_date: lead.expected_close_date?.slice(0, 10) ?? '', notes: lead.notes ?? '', stage: lead.stage }
    : { id: null, title: '', contact_name: '', email: '', phone: '', source: 'whatsapp', expected_revenue: 0, probability: null, expected_close_date: '', notes: '', stage })
  formErrors.value = {}
  modal.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formErrors.value = {}
  const { id, stage, ...fields } = form
  const body: Record<string, unknown> = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]))

  if (!id) {
    body.stage = stage
  }

  try {
    await api(id ? `${apiBase.value}/leads/${id}` : `${apiBase.value}/leads`, { method: id ? 'PUT' : 'POST', body })
    modal.value = false
    toast.success('Opportunité enregistrée.')
    await reload()
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

async function remove(): Promise<void> {
  if (!form.id || !window.confirm(`Supprimer l’opportunité « ${form.title} » ?`)) {
    return
  }

  try {
    await api(`${apiBase.value}/leads/${form.id}`, { method: 'DELETE' })
    modal.value = false
    toast.success('Opportunité supprimée.')
    await reload()
  }
  catch (requestError) {
    toast.error(errorMessage(requestError))
  }
}

function whatsappLink(phone: string): string {
  return `https://wa.me/${phone.replace(/[^\d]/g, '')}`
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" module="crm" title="CRM" subtitle="Suivez vos prospects jusqu’à la vente." icon="bi-kanban" @retry="refresh()">
    <template #actions>
      <button type="button" class="btn btn-primary" @click="openForm()"><i class="bi bi-plus-lg me-1" aria-hidden="true" />Nouvelle opportunité</button>
    </template>

    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
          <p class="small text-muted mb-1">Opportunités ouvertes</p>
          <p class="h4 fw-bold mb-0">{{ pipeline?.open_count ?? 0 }}</p>
        </div></div>
      </div>
      <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
          <p class="small text-muted mb-1">Prévision pondérée</p>
          <p class="h4 fw-bold mb-0">{{ formatPrice(pipeline?.weighted_forecast ?? 0, currency) }}</p>
        </div></div>
      </div>
      <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
          <p class="small text-muted mb-1">Taux de réussite</p>
          <p class="h4 fw-bold mb-0">{{ pipeline?.win_rate !== null && pipeline?.win_rate !== undefined ? `${formatNumber(pipeline.win_rate, 1)} %` : '—' }}</p>
        </div></div>
      </div>
    </div>

    <div class="mb-3" style="max-width: 24rem;">
      <label for="lead-search" class="visually-hidden">Rechercher une opportunité</label>
      <input id="lead-search" v-model="search" type="search" class="form-control" placeholder="Rechercher un prospect, un téléphone…">
    </div>

    <AppState v-if="leadStatus === 'pending' && !leads" loading text="Chargement du pipeline…" />
    <AppState v-else-if="leadError" variant="error" title="Pipeline indisponible" :text="errorMessage(leadError)">
      <button type="button" class="btn btn-primary" @click="reload()">Réessayer</button>
    </AppState>
    <div v-else class="kanban-board">
      <section
        v-for="column in columns"
        :key="column.value"
        class="kanban-column"
        :class="{ 'is-drop-target': dropTarget === column.value }"
        :aria-label="`Étape ${column.label}`"
        @dragover.prevent="dropTarget = column.value"
        @dragleave="dropTarget = dropTarget === column.value ? null : dropTarget"
        @drop.prevent="onDrop(column.value)"
      >
        <header class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <h2 class="h6 fw-bold mb-0">{{ column.label }} <span class="badge text-bg-light">{{ column.leads.length }}</span></h2>
            <p class="small text-muted mb-0">{{ formatPrice(column.totals?.expected_revenue ?? 0, currency) }}</p>
          </div>
          <button v-if="column.value !== 'won' && column.value !== 'lost'" type="button" class="btn btn-sm btn-light" :aria-label="`Ajouter une opportunité dans ${column.label}`" @click="openForm(undefined, column.value)">
            <i class="bi bi-plus" aria-hidden="true" />
          </button>
        </header>

        <article
          v-for="lead in column.leads"
          :key="lead.id"
          class="card kanban-card mb-2"
          draggable="true"
          @dragstart="dragged = lead"
          @dragend="dragged = null; dropTarget = null"
        >
          <div class="card-body p-2">
            <button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none" @click="openForm(lead)">{{ lead.title }}</button>
            <p class="small mb-1">{{ lead.contact_name }} · <span class="text-muted">{{ lead.source_label }}</span></p>
            <p class="small mb-1">
              <strong>{{ formatPrice(lead.expected_revenue, currency) }}</strong>
              <span class="text-muted"> · {{ lead.probability }} %</span>
            </p>
            <p v-if="lead.expected_close_date && !['won', 'lost'].includes(lead.stage)" class="small text-muted mb-1"><i class="bi bi-calendar-event me-1" aria-hidden="true" />{{ formatDay(lead.expected_close_date) }}</p>
            <p v-if="lead.lost_reason" class="small text-danger mb-1">{{ lead.lost_reason }}</p>
            <div class="d-flex gap-1 align-items-center">
              <a v-if="lead.phone" :href="whatsappLink(lead.phone)" target="_blank" rel="noopener" class="btn btn-sm btn-light" :aria-label="`Écrire à ${lead.contact_name} sur WhatsApp`"><i class="bi bi-whatsapp" aria-hidden="true" /></a>
              <span class="ms-auto" />
              <button v-if="stageIndex(lead.stage) > 0 && lead.stage !== 'lost'" type="button" class="btn btn-sm btn-light" :disabled="moving === lead.id" :aria-label="`Reculer « ${lead.title} » d’une étape`" @click="moveTo(lead, LEAD_STAGES[stageIndex(lead.stage) - 1]!.value)">
                <i class="bi bi-chevron-left" aria-hidden="true" />
              </button>
              <button v-if="stageIndex(lead.stage) < 3" type="button" class="btn btn-sm btn-light" :disabled="moving === lead.id" :aria-label="`Avancer « ${lead.title} » d’une étape`" @click="moveTo(lead, LEAD_STAGES[stageIndex(lead.stage) + 1]!.value)">
                <i class="bi bi-chevron-right" aria-hidden="true" />
              </button>
              <button v-if="!['won', 'lost'].includes(lead.stage)" type="button" class="btn btn-sm btn-light text-danger" :disabled="moving === lead.id" :aria-label="`Marquer « ${lead.title} » comme perdue`" @click="moveTo(lead, 'lost')">
                <i class="bi bi-x-circle" aria-hidden="true" />
              </button>
            </div>
          </div>
        </article>
        <p v-if="!column.leads.length" class="small text-muted text-center my-4">Glissez une opportunité ici.</p>
      </section>
    </div>

    <ErpModal v-model:open="lostModal" title="Pourquoi cette opportunité est-elle perdue ?" size="sm">
      <form id="lost-form" novalidate @submit.prevent="confirmLost">
        <label for="lost-reason" class="form-label">Raison</label>
        <input id="lost-reason" v-model="lostReason" type="text" maxlength="255" required class="form-control" placeholder="Prix trop élevé, délai, concurrent…">
      </form>
      <template #footer>
        <button type="button" class="btn btn-light" @click="lostModal = false">Annuler</button>
        <button type="submit" form="lost-form" class="btn btn-danger" :disabled="!lostReason.trim()">Marquer perdue</button>
      </template>
    </ErpModal>

    <ErpModal v-model:open="modal" :title="form.id ? 'Modifier l’opportunité' : 'Nouvelle opportunité'" size="lg">
      <form id="lead-form" novalidate @submit.prevent="save">
        <div class="row g-3">
          <div class="col-12">
            <label for="lead-title" class="form-label">Titre <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="lead-title" v-model="form.title" type="text" maxlength="160" required class="form-control" :class="{ 'is-invalid': formErrors.title }" placeholder="Ex. : 50 pagnes pour un mariage">
            <FieldError id="lead-title-error" :message="formErrors.title" />
          </div>
          <div class="col-md-6">
            <label for="lead-contact" class="form-label">Contact <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="lead-contact" v-model="form.contact_name" type="text" maxlength="120" required class="form-control" :class="{ 'is-invalid': formErrors.contact_name }">
            <FieldError id="lead-contact-error" :message="formErrors.contact_name" />
          </div>
          <div class="col-md-6">
            <label for="lead-source" class="form-label">Origine</label>
            <select id="lead-source" v-model="form.source" class="form-select" :class="{ 'is-invalid': formErrors.source }">
              <option v-for="item in LEAD_SOURCES" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
            <FieldError id="lead-source-error" :message="formErrors.source" />
          </div>
          <div class="col-md-6">
            <label for="lead-phone" class="form-label">Téléphone / WhatsApp</label>
            <input id="lead-phone" v-model="form.phone" type="tel" maxlength="40" class="form-control" :class="{ 'is-invalid': formErrors.phone }">
            <FieldError id="lead-phone-error" :message="formErrors.phone" />
          </div>
          <div class="col-md-6">
            <label for="lead-email" class="form-label">E-mail</label>
            <input id="lead-email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': formErrors.email }" aria-describedby="lead-email-help">
            <div id="lead-email-help" class="form-text">Avec un e-mail, le prospect devient client automatiquement une fois gagné.</div>
            <FieldError id="lead-email-error" :message="formErrors.email" />
          </div>
          <div class="col-md-4">
            <label for="lead-revenue" class="form-label">Montant espéré</label>
            <input id="lead-revenue" v-model.number="form.expected_revenue" type="number" min="0" step="1" class="form-control" :class="{ 'is-invalid': formErrors.expected_revenue }">
            <FieldError id="lead-revenue-error" :message="formErrors.expected_revenue" />
          </div>
          <div class="col-md-4">
            <label for="lead-probability" class="form-label">Probabilité (%)</label>
            <input id="lead-probability" v-model.number="form.probability" type="number" min="0" max="100" class="form-control" :class="{ 'is-invalid': formErrors.probability }" placeholder="Selon l’étape">
            <FieldError id="lead-probability-error" :message="formErrors.probability" />
          </div>
          <div class="col-md-4">
            <label for="lead-close" class="form-label">Clôture prévue</label>
            <input id="lead-close" v-model="form.expected_close_date" type="date" class="form-control" :class="{ 'is-invalid': formErrors.expected_close_date }">
            <FieldError id="lead-close-error" :message="formErrors.expected_close_date" />
          </div>
          <div class="col-12">
            <label for="lead-notes" class="form-label">Notes</label>
            <textarea id="lead-notes" v-model="form.notes" rows="3" class="form-control" />
          </div>
        </div>
      </form>
      <template #footer>
        <button v-if="form.id" type="button" class="btn btn-outline-danger me-auto" @click="remove">Supprimer</button>
        <button type="button" class="btn btn-light" @click="modal = false">Annuler</button>
        <button type="submit" form="lead-form" class="btn btn-primary" :disabled="saving">Enregistrer</button>
      </template>
    </ErpModal>
  </ErpPage>
</template>

<script setup lang="ts">
import type { ModerationStatus, Paginated, Product, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] })
useSeoMeta({ title: 'Modération', robots: 'noindex' })

const api = useApi()
const toast = useToast()

const FILTERS: { value: Exclude<ModerationStatus, 'pending'>, label: string }[] = [
  { value: 'pending_review', label: 'À examiner' },
  { value: 'rejected', label: 'Refusés' },
  { value: 'approved', label: 'Approuvés' },
]

const filter = ref<Exclude<ModerationStatus, 'pending'>>('pending_review')
const page = ref(1)
const notes = reactive<Record<number, string>>({})
const deciding = ref<number | null>(null)

const { data: queue, status, error, refresh } = useAsyncData(
  'admin-moderation',
  () => api<Paginated<Product>>('/admin/moderation', { query: { status: filter.value, page: page.value } }),
  { server: false, watch: [filter, page] },
)

function selectFilter(value: Exclude<ModerationStatus, 'pending'>): void {
  filter.value = value
  page.value = 1
}

function riskVariant(score: number): string {
  return score >= 70 ? 'danger' : score >= 30 ? 'warning' : 'success'
}

async function decide(product: Product, decision: 'approve' | 'reject'): Promise<void> {
  deciding.value = product.id

  try {
    const { data } = await api<Resource<Product>>(`/admin/moderation/${product.id}`, {
      method: 'POST',
      body: { decision, note: notes[product.id]?.trim() || undefined },
    })
    toast.success(decision === 'approve' ? `« ${data.name} » approuvé.` : `« ${data.name} » refusé.`)

    if (queue.value) {
      queue.value = {
        ...queue.value,
        data: data.moderation.status === filter.value
          ? queue.value.data.map(item => (item.id === data.id ? data : item))
          : queue.value.data.filter(item => item.id !== data.id),
      }
    }
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La décision n’a pas pu être enregistrée.'))
  }
  finally {
    deciding.value = null
  }
}
</script>

<template>
  <div>
    <div class="mb-4">
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-check me-2 text-primary" aria-hidden="true" />Modération des produits</h1>
      <p class="text-muted mb-0">Produits signalés par l’agent IA anti-fraude, en attente d’une décision humaine.</p>
    </div>

    <ul class="nav nav-pills gap-2 mb-4" aria-label="Filtrer par statut">
      <li v-for="item in FILTERS" :key="item.value" class="nav-item">
        <button type="button" class="nav-link" :class="{ active: filter === item.value }" :aria-pressed="filter === item.value" @click="selectFilter(item.value)">
          {{ item.label }}
        </button>
      </li>
    </ul>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement de la file de modération…" />
    <AppState v-else-if="error" variant="error" title="File de modération indisponible" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <div v-else-if="!queue?.data.length" class="card">
      <AppState icon="bi-check2-all" title="Rien à signaler" text="Aucun produit dans cette file. L’agent IA veille." />
    </div>
    <div v-else class="d-grid gap-3">
      <article v-for="product in queue.data" :key="product.id" class="card p-3 p-md-4" :aria-labelledby="`moderation-product-${product.id}`">
        <div class="row g-3">
          <div class="col-4 col-md-2">
            <div class="rounded-3 overflow-hidden">
              <ProductImage :src="product.image_url" :alt="product.name" ratio="1 / 1" />
            </div>
          </div>
          <div class="col-8 col-md-5">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
              <h2 :id="`moderation-product-${product.id}`" class="h6 fw-bold mb-0">{{ product.name }}</h2>
              <ModerationBadge :moderation="product.moderation" />
            </div>
            <p class="small text-muted mb-1">
              <i class="bi bi-shop me-1" aria-hidden="true" />
              <NuxtLink v-if="product.shop" :to="`/boutique/${product.shop.slug}`" target="_blank">{{ product.shop.name }}</NuxtLink>
              <template v-else>Boutique #{{ product.shop_id }}</template>
              · {{ formatDate(product.created_at) }}
            </p>
            <p class="mb-2"><PriceTag :amount="product.price" :currency="product.currency" :compare-at="product.compare_at_price" /></p>
            <p v-if="product.description" class="small text-muted mb-0">{{ product.description.slice(0, 220) }}{{ product.description.length > 220 ? '…' : '' }}</p>
          </div>
          <div class="col-md-5">
            <div class="d-flex justify-content-between small mb-1">
              <span class="fw-semibold">Score de risque</span>
              <span>{{ product.moderation.risk_score }}/100 · {{ product.moderation.engine === 'claude' ? 'IA avancée' : 'IA locale' }}</span>
            </div>
            <div class="progress mb-3" role="progressbar" :aria-label="`Score de risque de ${product.name}`" :aria-valuenow="product.moderation.risk_score" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
              <div class="progress-bar" :class="`bg-${riskVariant(product.moderation.risk_score)}`" :style="{ width: `${product.moderation.risk_score}%` }" />
            </div>
            <ul v-if="product.moderation.reasons.length" class="small ps-3 mb-3">
              <li v-for="reason in product.moderation.reasons" :key="reason">{{ reason }}</li>
            </ul>
            <p v-else class="small text-muted">Aucun motif relevé.</p>
            <label :for="`note-${product.id}`" class="form-label small">Note pour le marchand (optionnel)</label>
            <textarea :id="`note-${product.id}`" v-model="notes[product.id]" rows="2" class="form-control form-control-sm mb-2" />
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-success flex-grow-1" :disabled="deciding === product.id" @click="decide(product, 'approve')">
                <i class="bi bi-check-lg me-1" aria-hidden="true" />Approuver
              </button>
              <button type="button" class="btn btn-outline-danger flex-grow-1" :disabled="deciding === product.id" @click="decide(product, 'reject')">
                <i class="bi bi-x-lg me-1" aria-hidden="true" />Refuser
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
    <AppPagination v-model:page="page" :meta="queue?.meta" />
  </div>
</template>

<script setup lang="ts">
import type { Resource, Shop, ShopSetupSuggestion, Template } from '~/types'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Créer ma boutique', description: 'Décrivez votre activité, l’IA crée votre boutique en ligne.' })

const api = useApi()
const toast = useToast()
const route = useRoute()

/** Template pre-selected from the /modeles gallery (?modele=<key>), kept through the login redirect. */
const requestedTemplate = typeof route.query.modele === 'string' ? route.query.modele : null

const STEP_LABELS = ['Votre activité', 'Votre boutique', 'Votre style']
const DETAILS_FIELDS = ['name', 'tagline', 'description', 'sector', 'categories']

const step = ref(1)
const prompt = ref('')
const aiLoading = ref(false)
const aiError = ref('')
const submitting = ref(false)
const errors = ref<Record<string, string>>({})
const recommendedTemplate = ref<string | null>(null)
const primaryOverride = ref<string | null>(null)
const pickedFromGallery = ref(false)
const formatFilter = ref('all')

const form = reactive({
  name: '',
  tagline: '',
  description: '',
  sector: '',
  template: '',
  categories: [] as string[],
})

const { data: templates, status: templatesStatus, error: templatesError, refresh: refreshTemplates } = useTemplates()

const selectedTemplate = computed(() => templates.value?.find(template => template.key === form.template))
const previewTheme = computed(() => ({
  ...selectedTemplate.value?.theme,
  ...(primaryOverride.value ? { primary: primaryOverride.value } : {}),
}))
const filteredTemplates = computed(() => (templates.value ?? []).filter(template =>
  formatFilter.value === 'all' || resolveFormat(template.theme?.format) === formatFilter.value,
))
const progress = computed(() => Math.round((step.value / STEP_LABELS.length) * 100))

watch(templates, (list) => {
  if (!list?.length || form.template) {
    return
  }

  const requested = requestedTemplate ? list.find(template => template.key === requestedTemplate) : undefined
  form.template = requested?.key ?? list[0]!.key
  pickedFromGallery.value = Boolean(requested)
}, { immediate: true })

async function suggestWithAi(): Promise<void> {
  if (prompt.value.trim().length < 5) {
    aiError.value = 'Décrivez votre activité en quelques mots (ex. « Je vends des jus naturels à Dakar »).'

    return
  }

  aiLoading.value = true
  aiError.value = ''

  try {
    const { data } = await api<Resource<ShopSetupSuggestion>>('/ai/shop-setup', { method: 'POST', body: { prompt: prompt.value } })
    Object.assign(form, {
      name: data.name ?? '',
      tagline: data.tagline ?? '',
      description: data.description ?? '',
      sector: data.sector ?? '',
      categories: data.categories ?? [],
    })

    if (data.template) {
      recommendedTemplate.value = data.template

      // A template explicitly picked in the gallery wins over the AI suggestion.
      if (!pickedFromGallery.value) {
        form.template = data.template
      }
    }

    toast.success('L’IA a préparé votre boutique. Vérifiez et ajustez si besoin.')
    step.value = 2
  }
  catch (error) {
    aiError.value = errorMessage(error, 'L’IA n’a pas pu répondre. Vous pouvez remplir les informations vous-même.')
  }
  finally {
    aiLoading.value = false
  }
}

function goToStep(target: number): void {
  if (target === 3 && !form.name.trim()) {
    errors.value = { ...errors.value, name: 'Donnez un nom à votre boutique.' }

    return
  }

  step.value = target
}

function chooseTemplate(template: Template): void {
  form.template = template.key
  pickedFromGallery.value = true
  primaryOverride.value = null
}

async function submit(): Promise<void> {
  submitting.value = true
  errors.value = {}

  try {
    const { data: shop } = await api<Resource<Shop>>('/shops', {
      method: 'POST',
      body: {
        name: form.name,
        template: form.template,
        tagline: form.tagline || undefined,
        description: form.description || undefined,
        sector: form.sector || undefined,
        categories: form.categories,
        currency: 'XOF',
      },
    })

    if (primaryOverride.value && primaryOverride.value !== selectedTemplate.value?.theme.primary) {
      await api(`/shops/${shop.id}`, { method: 'PUT', body: { theme: { primary: primaryOverride.value } } }).catch(() => {
        toast.show('Boutique créée, mais la couleur personnalisée n’a pas pu être enregistrée.', 'warning')
      })
    }

    toast.success(`Félicitations ! « ${shop.name} » est en ligne.`)
    await navigateTo(`/dashboard/boutiques/${shop.id}`)
  }
  catch (error) {
    errors.value = validationErrors(error)
    const fields = Object.keys(errors.value)

    if (fields.some(field => DETAILS_FIELDS.some(name => field.startsWith(name)))) {
      step.value = 2
    }

    if (!fields.length) {
      toast.error(errorMessage(error, 'La création de la boutique a échoué.'))
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="container py-5" :style="{ maxWidth: step === 3 ? '1240px' : '920px' }">
    <div class="mb-4">
      <span class="section-eyebrow">Étape {{ step }} sur {{ STEP_LABELS.length }}</span>
      <h1 class="h1 mb-0">Créons votre boutique</h1>
      <p v-if="pickedFromGallery && selectedTemplate" class="small text-muted mt-2 mb-0">
        <i class="bi bi-check2-circle text-success me-1" aria-hidden="true" />Modèle choisi : <strong>{{ selectedTemplate.name }}</strong>
        ({{ formatLabel(selectedTemplate.theme?.format) }}). Vous pourrez le changer à l’étape 3.
      </p>
      <div
        class="progress mt-3"
        role="progressbar"
        aria-label="Progression de la création"
        :aria-valuenow="progress"
        aria-valuemin="0"
        aria-valuemax="100"
        style="height: 6px;"
      >
        <div class="progress-bar" :style="{ width: `${progress}%` }" />
      </div>
      <ol class="wizard-steps">
        <li v-for="(label, index) in STEP_LABELS" :key="label" :class="{ 'is-active': index + 1 <= step }" :aria-current="index + 1 === step ? 'step' : undefined">
          {{ index + 1 }}. {{ label }}
        </li>
      </ol>
    </div>

    <div v-if="step === 1" class="card ai-card p-4 p-md-5">
      <span class="icon-tile is-gold mb-3" aria-hidden="true"><i class="bi bi-magic" /></span>
      <label for="setup-prompt" class="h3 d-block">Décrivez votre activité en une phrase</label>
      <p id="setup-prompt-help" class="text-muted">L’IA vous propose un nom, un slogan, des catégories et le modèle idéal.</p>
      <textarea
        id="setup-prompt"
        v-model="prompt"
        class="form-control form-control-lg"
        :class="{ 'is-invalid': aiError }"
        rows="3"
        maxlength="500"
        placeholder="Ex. : Je vends des pagnes wax, des sacs et des bijoux faits main à Abidjan."
        aria-describedby="setup-prompt-help setup-prompt-error"
      />
      <FieldError id="setup-prompt-error" :message="aiError" />
      <div class="d-flex flex-column flex-sm-row gap-3 mt-4 align-items-sm-center">
        <button type="button" class="btn btn-primary btn-lg" :disabled="aiLoading" @click="suggestWithAi">
          <span v-if="aiLoading" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          <i v-else class="bi bi-stars me-1" aria-hidden="true" />
          {{ aiLoading ? 'L’IA réfléchit…' : 'Laisser l’IA proposer' }}
        </button>
        <button type="button" class="btn btn-link" @click="step = 2">Je préfère remplir moi-même</button>
      </div>
    </div>

    <form v-else-if="step === 2" class="card p-4 p-md-5" novalidate @submit.prevent="goToStep(3)">
      <h2 class="h3 mb-4">Les informations de votre boutique</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="shop-name" class="form-label">Nom de la boutique <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="shop-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" required maxlength="120" aria-describedby="shop-name-error">
          <FieldError id="shop-name-error" :message="errors.name" />
        </div>
        <div class="col-md-6">
          <label for="shop-sector" class="form-label">Secteur d’activité</label>
          <input id="shop-sector" v-model="form.sector" type="text" class="form-control" :class="{ 'is-invalid': errors.sector }" placeholder="Ex. : Mode, Beauté, Alimentation" aria-describedby="shop-sector-error">
          <FieldError id="shop-sector-error" :message="errors.sector" />
        </div>
        <div class="col-12">
          <label for="shop-tagline" class="form-label">Slogan</label>
          <input id="shop-tagline" v-model="form.tagline" type="text" class="form-control" :class="{ 'is-invalid': errors.tagline }" maxlength="160" aria-describedby="shop-tagline-error">
          <FieldError id="shop-tagline-error" :message="errors.tagline" />
        </div>
        <div class="col-12">
          <label for="shop-description" class="form-label">Description</label>
          <textarea id="shop-description" v-model="form.description" class="form-control" :class="{ 'is-invalid': errors.description }" rows="4" aria-describedby="shop-description-error" />
          <FieldError id="shop-description-error" :message="errors.description" />
        </div>
        <div class="col-12">
          <label for="shop-categories" class="form-label">Catégories</label>
          <TagInput id="shop-categories" v-model="form.categories" placeholder="Ajoutez une catégorie puis Entrée" describedby="shop-categories-help shop-categories-error" />
          <div id="shop-categories-help" class="form-text">Appuyez sur Entrée pour ajouter, cliquez sur × pour retirer.</div>
          <FieldError id="shop-categories-error" :message="errors.categories" />
        </div>
      </div>
      <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-light" @click="step = 1"><i class="bi bi-arrow-left me-1" aria-hidden="true" />Retour</button>
        <button type="submit" class="btn btn-primary">Continuer<i class="bi bi-arrow-right ms-1" aria-hidden="true" /></button>
      </div>
    </form>

    <form v-else class="row g-4" novalidate @submit.prevent="submit">
      <div class="col-lg-7 col-xl-8">
        <div class="card p-4 p-md-5 h-100">
          <h2 class="h3 mb-1">Choisissez votre modèle</h2>
          <p class="text-muted mb-4">Chaque modèle a sa structure de page. Vous pourrez tout modifier plus tard depuis votre tableau de bord.</p>
          <AppState v-if="templatesStatus === 'pending' || templatesStatus === 'idle'" loading text="Chargement des modèles…" />
          <AppState v-else-if="templatesError || !templates?.length" variant="error" title="Modèles indisponibles" text="Impossible de charger les modèles pour le moment.">
            <button type="button" class="btn btn-primary" @click="refreshTemplates()">Réessayer</button>
          </AppState>
          <template v-else>
            <FormatFilterChips v-model="formatFilter" class="mb-4" label="Filtrer les modèles par format" />
            <fieldset>
              <legend class="visually-hidden">Modèle de boutique</legend>
              <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
                <div v-for="template in filteredTemplates" :key="template.key" class="col">
                  <input
                    :id="`template-${template.key}`"
                    type="radio"
                    class="btn-check"
                    name="template"
                    :value="template.key"
                    :checked="form.template === template.key"
                    @change="chooseTemplate(template)"
                  >
                  <label :for="`template-${template.key}`" class="card template-option h-100 overflow-hidden position-relative">
                    <span class="template-thumb-wrap d-block p-2">
                      <TemplateThumbnail :theme="form.template === template.key ? previewTheme : template.theme" :label="`Aperçu schématique du modèle ${template.name}`" />
                    </span>
                    <span v-if="recommendedTemplate === template.key" class="badge text-bg-warning position-absolute top-0 start-0 m-2 rounded-pill shadow-sm">
                      <i class="bi bi-stars me-1" aria-hidden="true" />Recommandé par l’IA
                    </span>
                    <span class="template-check" aria-hidden="true"><i class="bi bi-check-lg" /></span>
                    <span class="d-block p-3">
                      <span class="d-flex justify-content-between align-items-start gap-2">
                        <span class="fw-semibold">{{ template.name }}</span>
                        <span class="format-badge flex-shrink-0">{{ formatLabel(template.theme?.format) }}</span>
                      </span>
                      <span class="d-block small text-muted mt-1">{{ template.description }}</span>
                      <span v-if="template.ideal_for?.length" class="d-flex flex-wrap gap-1 mt-2">
                        <span v-for="item in template.ideal_for.slice(0, 3)" :key="item" class="ideal-badge">{{ item }}</span>
                      </span>
                    </span>
                  </label>
                </div>
              </div>
            </fieldset>
            <p v-if="!filteredTemplates.length" class="text-muted mt-2">
              Aucun modèle pour ce format.
              <button type="button" class="btn btn-link p-0 align-baseline" @click="formatFilter = 'all'">Voir tous les modèles</button>
            </p>
            <FieldError id="template-error" :message="errors.template" />
          </template>
        </div>
      </div>

      <div class="col-lg-5 col-xl-4">
        <div class="card p-4 preview-sticky">
          <div class="d-none d-lg-block">
            <h2 class="h5 mb-3">Aperçu en direct</h2>
            <ThemePreview large :theme="previewTheme" :title="form.name || selectedTemplate?.name" :name="selectedTemplate?.name" />
            <p v-if="selectedTemplate" class="small text-muted mt-3 mb-0">
              <strong class="text-body">{{ selectedTemplate.name }}</strong> · format {{ formatLabel(selectedTemplate.theme?.format) }}
              <template v-if="selectedTemplate.ideal_for?.length"><br>Idéal pour : {{ selectedTemplate.ideal_for.join(', ') }}</template>
            </p>
            <hr class="my-4">
          </div>
          <div v-if="templates?.length" class="d-flex align-items-center gap-3 flex-wrap mb-4">
            <label for="primary-color" class="form-label mb-0">Couleur principale</label>
            <input
              id="primary-color"
              type="color"
              class="form-control form-control-color"
              :value="primaryOverride ?? selectedTemplate?.theme.primary ?? '#0F3D2E'"
              @input="primaryOverride = ($event.target as HTMLInputElement).value"
            >
            <button v-if="primaryOverride" type="button" class="btn btn-sm btn-link" @click="primaryOverride = null">Couleur du modèle</button>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="submitting || !form.template">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
            <i v-else class="bi bi-rocket-takeoff me-1" aria-hidden="true" />
            Créer ma boutique
          </button>
          <button type="button" class="btn btn-light w-100 mt-2" @click="step = 2"><i class="bi bi-arrow-left me-1" aria-hidden="true" />Retour</button>
        </div>
      </div>
    </form>
  </section>
</template>

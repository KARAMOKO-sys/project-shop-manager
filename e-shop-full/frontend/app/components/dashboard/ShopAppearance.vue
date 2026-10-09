<script setup lang="ts">
import type { HeroStyle, ProductLayout, Resource, Shop, ShopFormat, Template, Theme } from '~/types'

const props = defineProps<{ shop: Shop }>()
const emit = defineEmits<{ updated: [shop: Shop] }>()

const api = useApi()
const toast = useToast()

const { data: templates, status } = useTemplates()

const template = ref(props.shop.template)
const theme = reactive<Theme>(resolveTheme(props.shop.theme))
const saving = ref(false)

const radius = computed({
  get: () => radiusToPx(theme.radius),
  set: (value: number) => {
    theme.radius = `${value}px`
  },
})

const COLOR_FIELDS: { key: 'primary' | 'secondary' | 'background' | 'surface' | 'text', label: string }[] = [
  { key: 'primary', label: 'Principale' },
  { key: 'secondary', label: 'Secondaire' },
  { key: 'background', label: 'Fond' },
  { key: 'surface', label: 'Cartes' },
  { key: 'text', label: 'Texte' },
]

const HERO_STYLES: { value: HeroStyle, label: string }[] = [
  { value: 'gradient', label: 'Dégradé' },
  { value: 'image', label: 'Image' },
  { value: 'minimal', label: 'Minimaliste' },
]

const LAYOUTS: { value: ProductLayout, label: string }[] = [
  { value: 'grid', label: 'Grille' },
  { value: 'list', label: 'Liste' },
  { value: 'masonry', label: 'Mosaïque' },
]

function applyTemplate(candidate: Template): void {
  template.value = candidate.key
  Object.assign(theme, resolveTheme(candidate.theme))
}

function chooseFormat(format: ShopFormat): void {
  theme.format = format
}

async function save(): Promise<void> {
  saving.value = true

  try {
    const { data } = await api<Resource<Shop>>(`/shops/${props.shop.id}`, {
      method: 'PUT',
      body: { template: template.value, theme: { ...theme } },
    })
    emit('updated', data)
    toast.success('Apparence enregistrée. Votre boutique a un nouveau look !')
  }
  catch (error) {
    toast.error(errorMessage(error, 'L’apparence n’a pas pu être enregistrée.'))
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="row g-4" @submit.prevent="save">
    <div class="col-lg-7 d-grid gap-4">
      <div class="card p-4">
        <h2 class="h5 mb-1">Modèle</h2>
        <p class="small text-muted mb-3">Appliquer un modèle remplace le format, les couleurs, la police et le style.</p>
        <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des modèles…" />
        <p v-else-if="!templates?.length" class="text-muted mb-0">Modèles indisponibles pour le moment.</p>
        <fieldset v-else>
          <legend class="visually-hidden">Choisir un modèle</legend>
          <div class="row row-cols-2 row-cols-md-3 g-2">
            <div v-for="item in templates" :key="item.key" class="col">
              <input
                :id="`appearance-template-${item.key}`"
                type="radio"
                class="btn-check"
                name="appearance-template"
                :checked="template === item.key"
                @change="applyTemplate(item)"
              >
              <label :for="`appearance-template-${item.key}`" class="card template-option h-100 overflow-hidden position-relative">
                <span class="template-thumb-wrap d-block p-2">
                  <TemplateThumbnail :theme="item.theme" :label="`Aperçu schématique du modèle ${item.name}`" />
                </span>
                <span class="template-check" aria-hidden="true"><i class="bi bi-check-lg" /></span>
                <span class="d-block px-2 py-2">
                  <span class="small fw-semibold d-block">{{ item.name }}</span>
                  <span class="small text-muted">{{ formatLabel(item.theme?.format) }}</span>
                </span>
              </label>
            </div>
          </div>
        </fieldset>
      </div>

      <div class="card p-4">
        <h2 class="h5 mb-1">Format de la boutique</h2>
        <p class="small text-muted mb-3">La structure de votre page d’accueil, indépendamment des couleurs.</p>
        <fieldset>
          <legend class="visually-hidden">Choisir le format de la boutique</legend>
          <div class="row row-cols-2 row-cols-md-3 g-2">
            <div v-for="option in SHOP_FORMATS" :key="option.value" class="col">
              <input
                :id="`appearance-format-${option.value}`"
                type="radio"
                class="btn-check"
                name="appearance-format"
                :checked="theme.format === option.value"
                :aria-describedby="`appearance-format-${option.value}-help`"
                @change="chooseFormat(option.value)"
              >
              <label :for="`appearance-format-${option.value}`" class="card template-option format-option h-100 overflow-hidden position-relative">
                <span class="template-thumb-wrap d-block p-2">
                  <TemplateThumbnail :theme="theme" :format="option.value" />
                </span>
                <span class="template-check" aria-hidden="true"><i class="bi bi-check-lg" /></span>
                <span class="d-block px-2 py-2">
                  <span class="small fw-semibold d-block"><i class="bi me-1" :class="option.icon" aria-hidden="true" />{{ option.label }}</span>
                  <span :id="`appearance-format-${option.value}-help`" class="small text-muted d-block lh-sm">{{ option.description }}</span>
                </span>
              </label>
            </div>
          </div>
        </fieldset>
      </div>

      <div class="card p-4">
        <h2 class="h5 mb-3">Couleurs</h2>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-3">
          <div v-for="field in COLOR_FIELDS" :key="field.key" class="col">
            <label :for="`color-${field.key}`" class="form-label small">{{ field.label }}</label>
            <input :id="`color-${field.key}`" v-model="theme[field.key]" type="color" class="form-control form-control-color w-100">
          </div>
        </div>
      </div>

      <div class="card p-4">
        <h2 class="h5 mb-3">Style</h2>
        <p v-if="theme.format !== 'classic'" class="small text-muted mb-3">
          <i class="bi bi-info-circle me-1" aria-hidden="true" />La bannière d’accueil et l’affichage des produits s’appliquent surtout au format Classique.
        </p>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="theme-font" class="form-label">Police</label>
            <select id="theme-font" v-model="theme.font" class="form-select">
              <option v-for="font in THEME_FONTS" :key="font" :value="font">{{ font }}</option>
              <option v-if="!THEME_FONTS.includes(theme.font)" :value="theme.font">{{ theme.font }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="theme-radius" class="form-label">Arrondi des coins : {{ radius }} px</label>
            <input id="theme-radius" v-model.number="radius" type="range" class="form-range" min="0" max="32" step="2">
          </div>
          <div class="col-md-6">
            <label for="theme-hero" class="form-label">Bannière d’accueil</label>
            <select id="theme-hero" v-model="theme.hero_style" class="form-select">
              <option v-for="option in HERO_STYLES" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="theme-layout" class="form-label">Affichage des produits</label>
            <select id="theme-layout" v-model="theme.layout" class="form-select">
              <option v-for="option in LAYOUTS" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-4 position-sticky" style="top: 1rem;">
        <h2 class="h5 mb-3">Aperçu en direct</h2>
        <ThemePreview :theme="theme" :title="shop.name" :name="templates?.find(item => item.key === template)?.name ?? template" large />
        <p class="small text-muted mt-3 mb-0">Format : <strong class="text-body">{{ formatLabel(theme.format) }}</strong></p>
        <button type="submit" class="btn btn-primary btn-lg w-100 mt-4" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          Enregistrer l’apparence
        </button>
      </div>
    </div>
  </form>
</template>

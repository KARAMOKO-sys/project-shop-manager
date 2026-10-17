<script setup lang="ts">
import type { Category, DescriptionTone, Product, ProductStatus, Resource } from '~/types'

const props = defineProps<{
  shopId: number | string
  currency: string
  color?: string
  product?: Product | null
}>()
const emit = defineEmits<{ saved: [product: Product] }>()

const api = useApi()
const toast = useToast()
const { features: platformFeatures } = usePlatform()

const TONES: { value: DescriptionTone, label: string }[] = [
  { value: 'professionnel', label: 'Professionnel' },
  { value: 'decontracte', label: 'Décontracté' },
  { value: 'luxe', label: 'Luxe' },
]

const form = reactive({
  name: props.product?.name ?? '',
  price: props.product?.price ?? null as number | null,
  compare_at_price: props.product?.compare_at_price ?? null as number | null,
  stock_quantity: props.product?.stock_quantity ?? 1,
  category_id: props.product?.category_id ?? null as number | null,
  image_url: props.product?.image_url ?? '',
  tags: [...(props.product?.tags ?? [])],
  status: props.product?.status ?? 'published' as ProductStatus,
  description: props.product?.description ?? '',
})

const errors = ref<Record<string, string>>({})
const saving = ref(false)
const tone = ref<DescriptionTone>('professionnel')
const features = ref('')
const generating = ref(false)
const newCategory = ref('')
const addingCategory = ref(false)

const { data: categories, refresh: refreshCategories } = useAsyncData(
  `shop-categories-${props.shopId}`,
  () => api<Resource<Category[]>>(`/shops/${props.shopId}/categories`).then(response => response.data),
  { server: false, default: () => [] as Category[] },
)

const currencyLabel = computed(() => (['XOF', 'XAF'].includes(props.currency) ? 'FCFA' : props.currency))

async function generateDescription(): Promise<void> {
  if (!form.name.trim()) {
    errors.value = { ...errors.value, name: 'Indiquez d’abord le nom du produit pour que l’IA puisse écrire.' }

    return
  }

  generating.value = true

  try {
    const { data } = await api<Resource<{ description: string, tags: string[] }>>('/ai/product-description', {
      method: 'POST',
      body: { name: form.name, features: features.value || undefined, tone: tone.value },
    })
    form.description = data.description
    form.tags = Array.from(new Set([...form.tags, ...(data.tags ?? [])]))
    toast.success('Description générée ! Relisez-la et ajustez si besoin.')
  }
  catch (error) {
    toast.error(errorMessage(error, 'L’IA n’a pas pu générer la description.'))
  }
  finally {
    generating.value = false
  }
}

async function addCategory(): Promise<void> {
  const name = newCategory.value.trim()

  if (!name) {
    return
  }

  addingCategory.value = true

  try {
    const { data } = await api<Resource<Category>>(`/shops/${props.shopId}/categories`, { method: 'POST', body: { name } })
    await refreshCategories()
    form.category_id = data.id
    newCategory.value = ''
  }
  catch (error) {
    toast.error(validationErrors(error).name ?? errorMessage(error))
  }
  finally {
    addingCategory.value = false
  }
}

function onImageUploaded(url: string): void {
  form.image_url = url
  errors.value = { ...errors.value, image_url: '' }
  toast.success('Photo téléversée.')
}

async function submit(): Promise<void> {
  saving.value = true
  errors.value = {}

  const body = {
    ...form,
    image_url: form.image_url || null,
    compare_at_price: form.compare_at_price || null,
    description: form.description || null,
  }

  try {
    const url = props.product ? `/shops/${props.shopId}/products/${props.product.id}` : `/shops/${props.shopId}/products`
    const { data } = await api<Resource<Product>>(url, { method: props.product ? 'PUT' : 'POST', body })
    emit('saved', data)
  }
  catch (error) {
    errors.value = validationErrors(error)

    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Le produit n’a pas pu être enregistré.'))
    }
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="d-grid gap-4" novalidate @submit.prevent="submit">
    <div class="card p-4">
      <h2 class="h5 fw-bold mb-3">Informations</h2>
      <div class="row g-3">
        <div class="col-12">
          <label for="product-name" class="form-label">Nom du produit <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="product-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" required maxlength="160" aria-describedby="product-name-error">
          <FieldError id="product-name-error" :message="errors.name" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <label for="product-price" class="form-label">Prix ({{ currencyLabel }}) <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="product-price" v-model.number="form.price" type="number" min="0" step="any" inputmode="decimal" class="form-control" :class="{ 'is-invalid': errors.price }" required aria-describedby="product-price-error">
          <FieldError id="product-price-error" :message="errors.price" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <label for="product-compare" class="form-label">Prix barré ({{ currencyLabel }})</label>
          <input id="product-compare" v-model.number="form.compare_at_price" type="number" min="0" step="any" inputmode="decimal" class="form-control" :class="{ 'is-invalid': errors.compare_at_price }" aria-describedby="product-compare-help product-compare-error">
          <div id="product-compare-help" class="form-text">Optionnel, pour afficher une promotion.</div>
          <FieldError id="product-compare-error" :message="errors.compare_at_price" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <label for="product-stock" class="form-label">Stock</label>
          <input id="product-stock" v-model.number="form.stock_quantity" type="number" min="0" step="1" inputmode="numeric" class="form-control" :class="{ 'is-invalid': errors.stock_quantity }" aria-describedby="product-stock-error">
          <FieldError id="product-stock-error" :message="errors.stock_quantity" />
        </div>
        <div class="col-sm-6">
          <label for="product-category" class="form-label">Catégorie</label>
          <select id="product-category" v-model="form.category_id" class="form-select" :class="{ 'is-invalid': errors.category_id }" aria-describedby="product-category-error">
            <option :value="null">Sans catégorie</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <FieldError id="product-category-error" :message="errors.category_id" />
        </div>
        <div class="col-sm-6">
          <label for="product-new-category" class="form-label">Nouvelle catégorie</label>
          <div class="input-group">
            <input id="product-new-category" v-model="newCategory" type="text" class="form-control" placeholder="Ex. : Accessoires" @keydown.enter.prevent="addCategory">
            <button type="button" class="btn btn-outline-primary" :disabled="addingCategory || !newCategory.trim()" @click="addCategory">
              <span v-if="addingCategory" class="spinner-border spinner-border-sm" aria-hidden="true" />
              <template v-else>Ajouter</template>
            </button>
          </div>
        </div>
        <div class="col-12">
          <span id="product-status-label" class="form-label d-block">Statut</span>
          <div class="btn-group" role="group" aria-labelledby="product-status-label">
            <input id="status-published" v-model="form.status" type="radio" class="btn-check" value="published">
            <label for="status-published" class="btn btn-outline-primary"><i class="bi bi-globe me-1" aria-hidden="true" />Publié</label>
            <input id="status-draft" v-model="form.status" type="radio" class="btn-check" value="draft">
            <label for="status-draft" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true" />Brouillon</label>
          </div>
          <FieldError id="product-status-error" :message="errors.status" />
        </div>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="h5 fw-bold mb-3">Image</h2>
      <div class="row g-3 align-items-center">
        <div class="col-sm-8 d-grid gap-3">
          <DashboardImageUploadInput id="product-image-file" :shop-id="shopId" @uploaded="onImageUploaded" />
          <div>
            <label for="product-image" class="form-label">Ou collez l’URL d’une image</label>
            <input id="product-image" v-model.trim="form.image_url" type="url" class="form-control" :class="{ 'is-invalid': errors.image_url }" placeholder="https://…" aria-describedby="product-image-error">
            <FieldError id="product-image-error" :message="errors.image_url" />
          </div>
        </div>
        <div class="col-sm-4">
          <div class="rounded-3 overflow-hidden">
            <ProductImage :src="form.image_url" :alt="form.name ? `Aperçu : ${form.name}` : 'Aperçu de l’image'" :color="color" />
          </div>
        </div>
      </div>
    </div>

    <div class="card p-4 ai-card">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <h2 class="h5 fw-bold mb-1">Description</h2>
          <p v-if="platformFeatures.ai_assistant" class="small text-muted mb-0">Laissez l’IA rédiger une fiche produit qui donne envie d’acheter.</p>
        </div>
      </div>
      <div v-if="platformFeatures.ai_assistant" class="row g-2 align-items-end mb-3">
        <div class="col-md-6">
          <label for="product-features" class="form-label small">Points clés (optionnel)</label>
          <input id="product-features" v-model="features" type="text" class="form-control" placeholder="Ex. : 100 % coton, fait main, taille unique">
        </div>
        <div class="col-sm-6 col-md-3">
          <label for="product-tone" class="form-label small">Ton</label>
          <select id="product-tone" v-model="tone" class="form-select">
            <option v-for="option in TONES" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-3">
          <button type="button" class="btn btn-primary w-100" :disabled="generating" @click="generateDescription">
            <span v-if="generating" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
            <template v-else>✨ </template>Générer avec l’IA
          </button>
        </div>
      </div>
      <label for="product-description" class="form-label">Texte de la fiche</label>
      <textarea id="product-description" v-model="form.description" rows="7" class="form-control" :class="{ 'is-invalid': errors.description }" :aria-busy="generating" aria-describedby="product-description-error" />
      <FieldError id="product-description-error" :message="errors.description" />
      <label for="product-tags" class="form-label mt-3">Mots-clés</label>
      <TagInput id="product-tags" v-model="form.tags" placeholder="Ajoutez un mot-clé puis Entrée" describedby="product-tags-error" />
      <FieldError id="product-tags-error" :message="errors.tags" />
    </div>

    <div class="d-flex justify-content-end gap-2">
      <NuxtLink :to="`/dashboard/boutiques/${shopId}?onglet=produits`" class="btn btn-light">Annuler</NuxtLink>
      <button type="submit" class="btn btn-primary btn-lg" :disabled="saving">
        <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
        {{ product ? 'Enregistrer les modifications' : 'Enregistrer et faire vérifier' }}
      </button>
    </div>
  </form>
</template>

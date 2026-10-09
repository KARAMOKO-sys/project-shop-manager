<script setup lang="ts">
useSeoMeta({
  title: 'Modèles de boutique',
  description: 'Classique, éditorial, catalogue, menu, vitrine ou produit unique : choisissez le modèle de boutique en ligne qui correspond à votre activité.',
})

const route = useRoute()
const router = useRouter()

const { data: templates, status, error, refresh } = await useTemplates()

const formatFilter = computed<string>({
  get: () => (typeof route.query.format === 'string' && SHOP_FORMATS.some(option => option.value === route.query.format) ? route.query.format : 'all'),
  set: value => void router.replace({ query: { ...route.query, format: value === 'all' ? undefined : value } }),
})

const filteredTemplates = computed(() => (templates.value ?? []).filter(template =>
  formatFilter.value === 'all' || resolveFormat(template.theme?.format) === formatFilter.value,
))

const counts = computed(() => {
  const list = templates.value ?? []
  const result: Record<string, number> = { all: list.length }

  for (const option of SHOP_FORMATS) {
    result[option.value] = list.filter(template => resolveFormat(template.theme?.format) === option.value).length
  }

  return result
})

const activeFormat = computed(() => SHOP_FORMATS.find(option => option.value === formatFilter.value))
const fontsUrl = computed(() => googleFontsUrl((templates.value ?? []).map(template => template.theme?.font)))

useHead(() => ({
  link: fontsUrl.value ? [{ rel: 'stylesheet', href: fontsUrl.value, key: 'template-fonts' }] : [],
}))
</script>

<template>
  <div>
    <section class="home-hero py-5">
      <div class="container py-lg-4">
        <span class="section-eyebrow">Modèles de boutique</span>
        <h1 class="hero-title mb-3" style="font-size: clamp(2.2rem, 4.5vw, 3.4rem);">Une structure pensée pour <em>votre activité</em>.</h1>
        <p class="hero-lead mb-0">
          Chaque modèle combine un format de page — classique, éditorial, catalogue, menu, vitrine ou produit unique —
          et une palette adaptée à votre secteur. Tout reste personnalisable ensuite.
        </p>
      </div>
    </section>

    <section class="container py-5" aria-labelledby="gallery-title">
      <h2 id="gallery-title" class="visually-hidden">Galerie des modèles</h2>
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <FormatFilterChips v-model="formatFilter" :counts="templates?.length ? counts : undefined" />
        <p class="small text-muted mb-0" aria-live="polite">
          {{ filteredTemplates.length }} modèle(s)<template v-if="activeFormat"> · {{ activeFormat.description }}</template>
        </p>
      </div>

      <AppState v-if="status === 'pending'" loading text="Chargement des modèles…" />
      <AppState v-else-if="error" variant="error" title="Modèles indisponibles" text="Impossible de charger les modèles pour le moment.">
        <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
      </AppState>
      <AppState v-else-if="!filteredTemplates.length" icon="bi-palette" title="Aucun modèle pour ce format" text="Essayez un autre format ou affichez tous les modèles.">
        <button type="button" class="btn btn-outline-primary" @click="formatFilter = 'all'">Voir tous les modèles</button>
      </AppState>
      <div v-else class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        <div v-for="template in filteredTemplates" :key="template.key" class="col">
          <TemplateCard :template="template" />
        </div>
      </div>
    </section>

    <section class="container pb-5">
      <div class="cta-panel p-4 p-md-5 text-center">
        <h2 class="h2 gold-rule">Pas encore sûr ?</h2>
        <p class="text-muted mx-auto mt-3 mb-4" style="max-width: 34rem;">
          Décrivez votre activité en une phrase : l’IA vous recommande le modèle le plus adapté. Vous pourrez en changer à tout moment.
        </p>
        <NuxtLink to="/creer-ma-boutique" class="btn btn-primary btn-lg">Laisser l’IA choisir</NuxtLink>
      </div>
    </section>
  </div>
</template>

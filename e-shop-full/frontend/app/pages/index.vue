<script setup lang="ts">
import type { HomeData, Resource, Theme } from '~/types'

useSeoMeta({
  title: 'Créez votre boutique en ligne en 5 minutes grâce à l’IA',
  description: 'ShopAI aide les commerçants d’Afrique francophone à créer une boutique en ligne professionnelle en quelques minutes : IA, chatbot 24/7, vérification anti-fraude et Mobile Money.',
  ogTitle: 'ShopAI — Votre boutique en ligne propulsée par l’IA',
  ogDescription: 'Décrivez votre activité en une phrase, l’IA crée votre boutique.',
})

const api = useApi()
const { features: platformFeatures } = usePlatform()
const { data: home, status, error, refresh } = await useAsyncData('home', () =>
  api<Resource<HomeData>>('/home').then(response => response.data),
)

const loading = computed(() => status.value === 'pending')
const homeTemplates = computed(() => (home.value?.templates ?? []).slice(0, 6))
const allTemplatesLabel = computed(() => {
  const total = home.value?.templates?.length ?? 0

  return total > homeTemplates.value.length ? `Voir les ${total} modèles` : 'Voir tous les modèles'
})

const HERO_THEME: Partial<Theme> = {
  primary: '#0F3D2E',
  secondary: '#C8963E',
  background: '#FAF7F0',
  surface: '#ffffff',
  text: '#1C1B17',
  font: 'Fraunces',
  radius: '10px',
  format: 'editorial',
}

const trust = [
  { icon: 'bi-phone', label: 'Mobile Money' },
  { icon: 'bi-shield-check', label: 'Agent anti-fraude IA' },
  { icon: 'bi-chat-heart', label: 'Chatbot 24/7' },
]

const features = [
  { icon: 'bi-magic', title: 'Assistant IA', text: 'Décrivez votre activité en une phrase : l’IA propose le nom, les catégories, les textes et le design de votre boutique.' },
  { icon: 'bi-chat-heart', title: 'Chatbot 24h/24', text: 'Un vendeur virtuel répond à vos clients jour et nuit, recommande vos produits et rassure avant l’achat.' },
  { icon: 'bi-shield-check', title: 'Agent anti-fraude', text: 'Chaque produit est vérifié par notre agent IA avant publication : vos clients achètent en confiance.' },
  { icon: 'bi-phone', title: 'Mobile Money', text: 'Orange Money, MTN MoMo, Wave, Moov… et paiement à la livraison. Encaissez comme vos clients paient.' },
]

const steps = [
  { title: 'Décrivez', text: '« Je vends des pagnes wax et des sacs faits main à Abidjan. » Une phrase suffit.' },
  { title: 'Personnalisez', text: 'Choisissez un modèle, ajustez vos couleurs. L’IA a déjà tout préparé.' },
  { title: 'Vendez', text: 'Partagez votre lien sur WhatsApp, Facebook et TikTok. C’est en ligne !' },
]
</script>

<template>
  <div>
    <section class="home-hero py-5" aria-labelledby="hero-title">
      <div class="container py-lg-5">
        <div class="row align-items-center g-5">
          <div class="col-lg-6">
            <span class="section-eyebrow">Le e-commerce africain, en toute élégance</span>
            <h1 id="hero-title" class="hero-title mb-4">
              Votre boutique en ligne, <em>prête en 5 minutes.</em>
            </h1>
            <p class="hero-lead mb-4">
              Décrivez votre activité en une phrase. ShopAI crée votre boutique, rédige vos fiches produits,
              vérifie chaque article et répond à vos clients jour et nuit.
            </p>
            <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
              <NuxtLink to="/creer-ma-boutique" class="btn btn-primary btn-lg">
                Créer ma boutique <i class="bi bi-arrow-right ms-1" aria-hidden="true" />
              </NuxtLink>
              <NuxtLink to="/modeles" class="btn btn-outline-gold btn-lg">Voir les modèles</NuxtLink>
            </div>
            <ul class="trust-row" aria-label="Inclus dans chaque boutique">
              <li v-for="item in trust" :key="item.label"><i class="bi" :class="item.icon" aria-hidden="true" />{{ item.label }}</li>
            </ul>
            <dl v-if="home?.stats" class="hero-stats mt-5">
              <div>
                <dt class="small fw-normal text-muted">Boutiques actives</dt>
                <dd>{{ formatNumber(home.stats.shops) }}</dd>
              </div>
              <div>
                <dt class="small fw-normal text-muted">Produits vérifiés</dt>
                <dd>{{ formatNumber(home.stats.products) }}</dd>
              </div>
            </dl>
          </div>
          <div class="col-lg-6">
            <div class="hero-frame mx-lg-4">
              <ThemePreview large title="Maison Adjoa" name="Éditorial" :theme="HERO_THEME" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="produits" class="container py-5 my-lg-3" aria-labelledby="featured-title">
      <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
          <span class="section-eyebrow">Sélection</span>
          <h2 id="featured-title" class="h1 mb-0 gold-rule">Produits à la une</h2>
        </div>
        <span class="text-muted small"><i class="bi bi-shield-check text-success me-1" aria-hidden="true" />Tous vérifiés par notre agent IA</span>
      </div>
      <AppState v-if="loading" loading text="Chargement des produits…" />
      <AppState
        v-else-if="error"
        variant="error"
        title="Impossible de charger les produits"
        text="Le service est momentanément indisponible."
      >
        <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
      </AppState>
      <AppState
        v-else-if="!home?.featured_products?.length"
        icon="bi-bag-heart"
        title="Bientôt des pépites ici"
        text="Les premiers produits des boutiques ShopAI apparaîtront très vite."
      />
      <div v-else class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
        <div v-for="product in home.featured_products" :key="product.id" class="col">
          <ProductCard :product="product" show-shop />
        </div>
      </div>
    </section>

    <section id="modeles" class="section-surface py-5" aria-labelledby="templates-title">
      <div class="container py-lg-3">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
          <div>
            <span class="section-eyebrow">Modèles de boutique</span>
            <h2 id="templates-title" class="h1 mb-0 gold-rule">Un format pour chaque activité</h2>
            <p class="text-muted mt-3 mb-0" style="max-width: 36rem;">
              Boutique classique, magazine, catalogue, carte de restaurant, vitrine de luxe ou page produit unique.
            </p>
          </div>
          <NuxtLink v-if="homeTemplates.length" to="/modeles" class="btn btn-outline-gold">
            {{ allTemplatesLabel }} <i class="bi bi-arrow-right ms-1" aria-hidden="true" />
          </NuxtLink>
        </div>
        <AppState v-if="loading" loading text="Chargement des modèles…" />
        <AppState v-else-if="!homeTemplates.length" icon="bi-palette" title="Modèles indisponibles" text="Revenez dans un instant pour découvrir nos modèles.">
          <NuxtLink to="/modeles" class="btn btn-outline-primary">Ouvrir la galerie</NuxtLink>
        </AppState>
        <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
          <div v-for="template in homeTemplates" :key="template.key" class="col">
            <TemplateCard :template="template" />
          </div>
        </div>
      </div>
    </section>

    <section class="container py-5 my-lg-3" aria-labelledby="steps-title">
      <div class="text-center mb-5">
        <span class="section-eyebrow">Comment ça marche</span>
        <h2 id="steps-title" class="h1 gold-rule">Trois étapes, cinq minutes</h2>
      </div>
      <ol class="row g-4 list-unstyled mb-0">
        <li v-for="(step, index) in steps" :key="step.title" class="col-md-4">
          <div class="step-card">
            <span class="step-number" aria-hidden="true">0{{ index + 1 }}</span>
            <h3 class="h4">{{ step.title }}</h3>
            <p class="text-muted mb-0">{{ step.text }}</p>
          </div>
        </li>
      </ol>
    </section>

    <section id="boutiques" class="container pb-5" aria-labelledby="shops-title">
      <div class="mb-4">
        <span class="section-eyebrow">Communauté</span>
        <h2 id="shops-title" class="h1 mb-0 gold-rule">Boutiques populaires</h2>
      </div>
      <AppState v-if="loading" loading text="Chargement des boutiques…" />
      <AppState v-else-if="!home?.shops?.length" icon="bi-shop" title="Soyez la première boutique !" text="Créez votre boutique et apparaissez ici.">
        <NuxtLink to="/creer-ma-boutique" class="btn btn-primary">Créer ma boutique</NuxtLink>
      </AppState>
      <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
        <div v-for="shop in home.shops" :key="shop.id" class="col">
          <article class="card h-100 card-hover overflow-hidden">
            <div class="shop-card-band" :style="{ background: `linear-gradient(90deg, ${shop.theme?.primary ?? '#0F3D2E'}, ${shop.theme?.secondary ?? '#C8963E'})` }" />
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="shop-logo-mark" :style="{ background: shop.theme?.primary ?? '#0F3D2E', color: contrastText(shop.theme?.primary ?? '#0F3D2E') }" aria-hidden="true">
                  <img v-if="shop.logo_url" :src="shop.logo_url" alt="">
                  <template v-else>{{ shop.name.charAt(0).toUpperCase() }}</template>
                </span>
                <h3 class="h5 mb-0">
                  <NuxtLink :to="`/boutique/${shop.slug}`" class="stretched-link text-reset text-decoration-none">{{ shop.name }}</NuxtLink>
                </h3>
              </div>
              <p class="small text-muted mb-2">{{ shop.tagline ?? shop.description }}</p>
              <span v-if="shop.products_count !== undefined" class="small fw-semibold text-gold">
                <i class="bi bi-box-seam me-1" aria-hidden="true" />{{ shop.products_count }} produit(s)
              </span>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section id="fonctionnalites" class="reassurance-strip py-5" aria-labelledby="features-title">
      <div class="container py-lg-3">
        <div class="text-center mb-5">
          <span class="section-eyebrow" style="color: var(--app-gold);">Vos alliés au quotidien</span>
          <h2 id="features-title" class="h1 gold-rule" style="color: var(--app-ivory);">L’IA travaille pour vous</h2>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
          <div v-for="feature in features" :key="feature.title" class="col">
            <span class="icon-tile mb-3" aria-hidden="true"><i class="bi" :class="feature.icon" /></span>
            <h3 class="h5">{{ feature.title }}</h3>
            <p class="mb-0 small">{{ feature.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="container py-5 my-lg-3">
      <div class="cta-panel p-4 p-md-5 text-center">
        <h2 class="h1 gold-rule">Prêt à vendre en ligne ?</h2>
        <p class="text-muted mx-auto mt-3 mb-4" style="max-width: 34rem;">Votre boutique est prête en moins de temps qu’il n’en faut pour préparer un attiéké.</p>
        <NuxtLink to="/creer-ma-boutique" class="btn btn-primary btn-lg">Créer ma boutique gratuitement</NuxtLink>
      </div>
    </section>

    <ChatWidget
      v-if="platformFeatures.platform_chat"
      endpoint="/chat"
      :suggestions="['Comment créer ma boutique ?', 'Quels moyens de paiement ?', 'Combien ça coûte ?']"
      welcome="Bonjour ! Je suis l’assistant ShopAI. Posez-moi vos questions sur la création de votre boutique en ligne."
    />
  </div>
</template>

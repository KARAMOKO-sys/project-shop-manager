<script setup lang="ts">
const { config, features } = usePlatform()

const whatsappUrl = computed(() => {
  const digits = config.value.platform.support_whatsapp?.replace(/\D/g, '')

  return digits ? `https://wa.me/${digits}` : null
})
</script>

<template>
  <footer class="app-footer mt-auto">
    <div class="footer-motif" aria-hidden="true" />
    <div class="container pt-5 pb-4">
      <div class="row g-4">
        <div class="col-lg-5">
          <NuxtLink to="/" class="brand-logo mb-3" :aria-label="`${config.platform.name}, accueil`">
            <AppBrand />
          </NuxtLink>
          <p class="mt-3 mb-0" style="max-width: 26rem;">{{ config.platform.tagline }}</p>
          <ul v-if="config.platform.support_email || whatsappUrl" class="list-unstyled d-grid gap-1 mt-3 mb-0 small">
            <li v-if="config.platform.support_email">
              <i class="bi bi-envelope me-1" aria-hidden="true" /><a :href="`mailto:${config.platform.support_email}`">{{ config.platform.support_email }}</a>
            </li>
            <li v-if="whatsappUrl">
              <i class="bi bi-whatsapp me-1" aria-hidden="true" /><a :href="whatsappUrl" target="_blank" rel="noopener">{{ config.platform.support_whatsapp }}</a>
            </li>
          </ul>
        </div>
        <div class="col-6 col-lg-3">
          <h2 class="mb-3">Plateforme</h2>
          <ul class="list-unstyled d-grid gap-2 mb-0">
            <li v-if="features.shop_creation"><NuxtLink to="/creer-ma-boutique">Créer ma boutique</NuxtLink></li>
            <li><NuxtLink to="/modeles">Modèles de boutique</NuxtLink></li>
            <li><NuxtLink to="/#fonctionnalites">Fonctionnalités</NuxtLink></li>
          </ul>
        </div>
        <div class="col-6 col-lg-4">
          <h2 class="mb-3">Compte</h2>
          <ul class="list-unstyled d-grid gap-2 mb-0">
            <li><NuxtLink to="/login">Connexion</NuxtLink></li>
            <li v-if="features.registration"><NuxtLink to="/inscription">Inscription</NuxtLink></li>
            <li><NuxtLink to="/dashboard">Tableau de bord</NuxtLink></li>
          </ul>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small">
        <p class="mb-0">© {{ new Date().getFullYear() }} {{ config.platform.name }}. Fait avec passion pour les entrepreneurs africains.</p>
        <p class="mb-0"><i class="bi bi-phone me-1" aria-hidden="true" />Mobile Money · Paiement à la livraison</p>
      </div>
    </div>
  </footer>
</template>

<template>
  <footer class="app-footer mt-auto">
    <div class="footer-motif" aria-hidden="true" />
    <div class="container pt-5 pb-4">
      <div class="row g-4">
        <div class="col-lg-5">
          <NuxtLink to="/" class="brand-logo mb-3" aria-label="ShopAI, accueil">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-stars" /></span>
            <span>Shop<span class="brand-ai">AI</span></span>
          </NuxtLink>
          <p class="mt-3 mb-0" style="max-width: 26rem;">
            La plateforme e-commerce propulsée par l’IA pour les commerçants d’Afrique francophone.
            Vendez en ligne, encaissez en Mobile Money, laissez l’IA faire le reste.
          </p>
        </div>
        <div class="col-6 col-lg-3">
          <h2 class="mb-3">Plateforme</h2>
          <ul class="list-unstyled d-grid gap-2 mb-0">
            <li><NuxtLink to="/creer-ma-boutique">Créer ma boutique</NuxtLink></li>
            <li><NuxtLink to="/modeles">Modèles de boutique</NuxtLink></li>
            <li><NuxtLink to="/#fonctionnalites">Fonctionnalités</NuxtLink></li>
          </ul>
        </div>
        <div class="col-6 col-lg-4">
          <h2 class="mb-3">Compte</h2>
          <ul class="list-unstyled d-grid gap-2 mb-0">
            <li><NuxtLink to="/login">Connexion</NuxtLink></li>
            <li><NuxtLink to="/inscription">Inscription</NuxtLink></li>
            <li><NuxtLink to="/dashboard">Tableau de bord</NuxtLink></li>
          </ul>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small">
        <p class="mb-0">© {{ new Date().getFullYear() }} ShopAI. Fait avec passion pour les entrepreneurs africains.</p>
        <p class="mb-0"><i class="bi bi-phone me-1" aria-hidden="true" />Mobile Money · Paiement à la livraison</p>
      </div>
    </div>
  </footer>
</template>

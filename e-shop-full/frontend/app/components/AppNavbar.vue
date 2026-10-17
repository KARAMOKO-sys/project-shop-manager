<script setup lang="ts">
const { user, isAuthenticated, isAdmin, logout } = useAuth()
const { config, features } = usePlatform()

useCloseOnNavigate('#mainNav', 'Collapse')
</script>

<template>
  <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3" aria-label="Navigation principale">
    <div class="container">
      <NuxtLink to="/" class="brand-logo" :aria-label="`${config.platform.name}, accueil`">
        <AppBrand />
      </NuxtLink>
      <button
        class="navbar-toggler border-0"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#mainNav"
        aria-controls="mainNav"
        aria-expanded="false"
        aria-label="Afficher le menu"
      >
        <span class="navbar-toggler-icon" />
      </button>
      <div id="mainNav" class="collapse navbar-collapse">
        <ul class="navbar-nav mx-auto gap-lg-3">
          <li class="nav-item"><NuxtLink class="nav-link" to="/">Accueil</NuxtLink></li>
          <li class="nav-item"><NuxtLink class="nav-link" to="/modeles">Modèles</NuxtLink></li>
          <li class="nav-item"><NuxtLink class="nav-link" to="/#produits">Produits</NuxtLink></li>
          <li class="nav-item"><NuxtLink class="nav-link" to="/#fonctionnalites">Fonctionnalités</NuxtLink></li>
        </ul>
        <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center mt-3 mt-lg-0">
          <template v-if="isAuthenticated">
            <div class="dropdown">
              <button class="btn btn-light dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1" aria-hidden="true" />
                {{ user?.name ?? 'Mon compte' }}
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><NuxtLink class="dropdown-item" to="/dashboard"><i class="bi bi-grid me-2" aria-hidden="true" />Tableau de bord</NuxtLink></li>
                <li v-if="isAdmin"><NuxtLink class="dropdown-item" to="/admin/moderation"><i class="bi bi-shield-check me-2" aria-hidden="true" />Modération</NuxtLink></li>
                <li v-if="isAdmin"><NuxtLink class="dropdown-item" to="/admin/parametres"><i class="bi bi-sliders me-2" aria-hidden="true" />Paramètres de la plateforme</NuxtLink></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <button type="button" class="dropdown-item" @click="logout">
                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true" />Se déconnecter
                  </button>
                </li>
              </ul>
            </div>
          </template>
          <NuxtLink v-else to="/login" class="btn btn-light">Connexion</NuxtLink>
          <NuxtLink v-if="features.shop_creation" to="/creer-ma-boutique" class="btn btn-primary">
            Créer ma boutique
          </NuxtLink>
        </div>
      </div>
    </div>
  </nav>
</template>

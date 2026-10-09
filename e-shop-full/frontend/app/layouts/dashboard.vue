<script setup lang="ts">
const { user, isAdmin, logout } = useAuth()

useCloseOnNavigate('#dashboardSidebar', 'Offcanvas')

const links = computed(() => [
  { to: '/dashboard', label: 'Mes boutiques', icon: 'bi-grid-1x2' },
  { to: '/creer-ma-boutique', label: 'Nouvelle boutique', icon: 'bi-plus-circle' },
  ...(isAdmin.value ? [{ to: '/admin/moderation', label: 'Modération', icon: 'bi-shield-check' }] : []),
  { to: '/', label: 'Retour au site', icon: 'bi-house' },
])
</script>

<template>
  <div class="d-lg-flex">
    <header class="navbar bg-white border-bottom sticky-top d-lg-none px-3">
      <NuxtLink to="/dashboard" class="brand-logo" aria-label="ShopAI, tableau de bord">
        <span class="brand-mark" aria-hidden="true"><i class="bi bi-stars" /></span>
        <span>Shop<span class="brand-ai">AI</span></span>
      </NuxtLink>
      <button
        type="button"
        class="btn btn-light"
        data-bs-toggle="offcanvas"
        data-bs-target="#dashboardSidebar"
        aria-controls="dashboardSidebar"
        aria-label="Ouvrir le menu du tableau de bord"
      >
        <i class="bi bi-list fs-5" aria-hidden="true" />
      </button>
    </header>

    <aside
      id="dashboardSidebar"
      class="offcanvas-lg offcanvas-start dashboard-sidebar"
      tabindex="-1"
      aria-labelledby="dashboardSidebarLabel"
    >
      <div class="offcanvas-header">
        <span id="dashboardSidebarLabel" class="brand-logo">
          <span class="brand-mark" aria-hidden="true"><i class="bi bi-stars" /></span>
          <span>Shop<span class="brand-ai">AI</span></span>
        </span>
        <button
          type="button"
          class="btn-close d-lg-none"
          data-bs-dismiss="offcanvas"
          data-bs-target="#dashboardSidebar"
          aria-label="Fermer le menu"
        />
      </div>
      <div class="offcanvas-body d-flex flex-column p-3 h-100">
        <NuxtLink to="/dashboard" class="brand-logo d-none d-lg-inline-flex px-2 mb-4" aria-label="ShopAI, tableau de bord">
          <span class="brand-mark" aria-hidden="true"><i class="bi bi-stars" /></span>
          <span>Shop<span class="brand-ai">AI</span></span>
        </NuxtLink>
        <nav aria-label="Menu du tableau de bord">
          <ul class="nav flex-column gap-1">
            <li v-for="link in links" :key="link.to" class="nav-item">
              <NuxtLink :to="link.to" class="nav-link">
                <i class="bi" :class="link.icon" aria-hidden="true" /> {{ link.label }}
              </NuxtLink>
            </li>
          </ul>
        </nav>
        <div class="sidebar-footer mt-auto pt-4">
          <p class="small mb-2 text-muted text-truncate">
            <i class="bi bi-person-circle me-1" aria-hidden="true" />{{ user?.name ?? 'Mon compte' }}
          </p>
          <button type="button" class="btn btn-light btn-sm w-100" @click="logout">
            <i class="bi bi-box-arrow-right me-1" aria-hidden="true" /> Se déconnecter
          </button>
        </div>
      </div>
    </aside>

    <main id="contenu" class="flex-grow-1 dashboard-main p-3 p-lg-4">
      <slot />
    </main>

    <ChatWidget
      endpoint="/chat"
      title="Coach ShopAI"
      subtitle="Conseils pour vendre plus, 24h/24"
      welcome="Bonjour ! Je peux vous aider à rédiger vos fiches produits, fixer vos prix ou attirer plus de clients."
      :suggestions="['Comment vendre plus ?', 'Comment fixer mes prix ?', 'Activer Mobile Money']"
    />
  </div>
</template>

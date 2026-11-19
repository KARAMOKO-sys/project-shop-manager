<script setup lang="ts">
import type { ErpModule, Resource } from '~/types'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const { shop, loading, error, refresh, apiBase } = useErpShop(() => String(route.params.id))

useSeoMeta({ title: 'Applications' })

const { data: catalog, status, error: catalogError, refresh: refreshCatalog } = useAsyncData(
  () => `erp-modules-${route.params.id}`,
  () => api<Resource<ErpModule[]>>(`${apiBase.value}/modules`).then(response => response.data),
  { server: false },
)

const busy = ref<string | null>(null)
const filter = ref<'all' | 'installed' | 'available'>('all')

const groups = computed(() => {
  const modules = (catalog.value ?? []).filter(module => filter.value === 'all' || (filter.value === 'installed') === module.installed)
  const byCategory = new Map<string, ErpModule[]>()

  for (const module of modules) {
    byCategory.set(module.category, [...(byCategory.get(module.category) ?? []), module])
  }

  return [...byCategory.entries()].map(([category, items]) => ({ category, items }))
})

const nameOf = (key: string) => catalog.value?.find(module => module.key === key)?.name ?? MODULE_NAMES[key] ?? key

function syncShopModules(modules: ErpModule[]): void {
  catalog.value = modules

  if (shop.value) {
    shop.value = { ...shop.value, modules: modules.filter(module => module.installed).map(module => module.key) }
  }
}

async function install(module: ErpModule): Promise<void> {
  const missing = module.depends.filter(key => !catalog.value?.find(item => item.key === key)?.installed)

  if (missing.length && !window.confirm(`« ${module.name} » a besoin de : ${missing.map(nameOf).join(', ')}. Ces applications seront aussi installées. Continuer ?`)) {
    return
  }

  busy.value = module.key

  try {
    const response = await api<{ data: ErpModule[], installed: string[] }>(`${apiBase.value}/modules/${module.key}`, { method: 'POST' })
    syncShopModules(response.data)
    toast.success(response.installed.length ? `Installé : ${response.installed.map(nameOf).join(', ')}.` : `« ${module.name} » est déjà installée.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'L’installation a échoué.'))
  }
  finally {
    busy.value = null
  }
}

async function uninstall(module: ErpModule): Promise<void> {
  if (!window.confirm(`Désinstaller « ${module.name} » ? Ses écrans disparaissent mais vos données sont conservées : une réinstallation les restaure.`)) {
    return
  }

  busy.value = module.key

  try {
    const response = await api<Resource<ErpModule[]>>(`${apiBase.value}/modules/${module.key}`, { method: 'DELETE' })
    syncShopModules(response.data)
    toast.success(`« ${module.name} » désinstallée.`)
  }
  catch (requestError) {
    toast.error(errorMessage(requestError, 'La désinstallation a échoué.'))
  }
  finally {
    busy.value = null
  }
}
</script>

<template>
  <ErpPage :shop="shop" :loading="loading" :error="error" title="Applications" subtitle="Composez votre ERP : installez uniquement les applications utiles à votre activité." icon="bi-grid-3x3-gap" @retry="refresh()">
    <template #actions>
      <div class="btn-group" role="group" aria-label="Filtrer les applications">
        <button v-for="option in [{ v: 'all', l: 'Toutes' }, { v: 'installed', l: 'Installées' }, { v: 'available', l: 'Disponibles' }] as const" :key="option.v" type="button" class="btn btn-sm" :class="filter === option.v ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="filter === option.v" @click="filter = option.v">
          {{ option.l }}
        </button>
      </div>
    </template>

    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement du catalogue…" />
    <AppState v-else-if="catalogError" variant="error" title="Catalogue indisponible" :text="errorMessage(catalogError)">
      <button type="button" class="btn btn-primary" @click="refreshCatalog()">Réessayer</button>
    </AppState>
    <AppState v-else-if="!groups.length" icon="bi-grid" title="Aucune application dans ce filtre" />
    <template v-else>
      <section v-for="group in groups" :key="group.category" class="mb-4" :aria-labelledby="`category-${group.category}`">
        <h2 :id="`category-${group.category}`" class="h6 text-uppercase text-muted fw-bold mb-3">{{ group.category }}</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
          <div v-for="module in group.items" :key="module.key" class="col">
            <article class="card app-card h-100 p-4" :class="{ 'is-installed': module.installed }">
              <div class="d-flex align-items-start gap-3 mb-2">
                <span class="icon-tile" :class="{ 'is-gold': module.installed }" aria-hidden="true"><i class="bi" :class="module.icon" /></span>
                <div class="flex-grow-1 min-w-0">
                  <h3 class="h6 fw-bold mb-1">{{ module.name }}</h3>
                  <span v-if="module.core" class="badge text-bg-dark">Socle</span>
                  <span v-else-if="module.installed" class="badge text-bg-success"><i class="bi bi-check2 me-1" aria-hidden="true" />Installée</span>
                  <span v-else class="badge text-bg-light border">Disponible</span>
                </div>
              </div>
              <p class="small text-muted flex-grow-1">{{ module.summary }}</p>
              <p v-if="module.depends.length" class="small mb-2"><i class="bi bi-link-45deg me-1" aria-hidden="true" />Nécessite : {{ module.depends.map(nameOf).join(', ') }}</p>
              <p v-if="module.installed && module.required_by.length" class="small text-muted mb-2">Utilisée par : {{ module.required_by.map(nameOf).join(', ') }}</p>
              <p v-if="module.installed_at" class="small text-muted mb-2">Installée le {{ formatShortDate(module.installed_at) }}</p>
              <div class="mt-2">
                <button v-if="!module.installed" type="button" class="btn btn-primary w-100" :disabled="busy !== null" @click="install(module)">
                  <span v-if="busy === module.key" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                  <i v-else class="bi bi-download me-1" aria-hidden="true" />Installer
                </button>
                <button v-else-if="!module.core" type="button" class="btn btn-outline-danger w-100" :disabled="busy !== null || module.required_by.length > 0" :title="module.required_by.length ? 'Désinstallez d’abord les applications qui en dépendent' : undefined" @click="uninstall(module)">
                  <span v-if="busy === module.key" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                  Désinstaller
                </button>
                <NuxtLink v-else :to="`/dashboard/boutiques/${shop!.id}`" class="btn btn-light w-100">Ouvrir la boutique</NuxtLink>
              </div>
            </article>
          </div>
        </div>
      </section>
    </template>
  </ErpPage>
</template>

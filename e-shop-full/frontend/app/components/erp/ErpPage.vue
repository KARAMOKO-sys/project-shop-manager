<script setup lang="ts">
import type { Shop } from '~/types'

/**
 * Frame of every ERP page: loading/error states, module navigation, and an
 * invitation to install the page's application when it is missing.
 */
const props = defineProps<{
  shop: Shop | null | undefined
  loading: boolean
  error?: unknown
  module?: string
  title: string
  subtitle?: string
  icon?: string
}>()
defineEmits<{ retry: [] }>()

const installed = computed(() => !props.module || (props.shop?.modules ?? []).includes(props.module))
</script>

<template>
  <div>
    <AppState v-if="loading" loading text="Chargement…" />
    <AppState v-else-if="error || !shop" variant="error" title="Boutique introuvable" :text="errorMessage(error, 'Cette boutique n’existe pas ou ne vous appartient pas.')">
      <div class="d-flex gap-2 justify-content-center">
        <button type="button" class="btn btn-primary" @click="$emit('retry')">Réessayer</button>
        <NuxtLink to="/dashboard" class="btn btn-light">Mes boutiques</NuxtLink>
      </div>
    </AppState>
    <template v-else>
      <ErpNav :shop="shop" />
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 d-print-none">
        <div class="d-flex align-items-center gap-3">
          <span v-if="icon" class="icon-tile" aria-hidden="true"><i class="bi" :class="icon" /></span>
          <div>
            <h1 class="h3 fw-bold mb-0">{{ title }}</h1>
            <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
          </div>
        </div>
        <div v-if="installed && $slots.actions" class="d-flex gap-2 flex-wrap">
          <slot name="actions" />
        </div>
      </div>
      <div v-if="!installed" class="card">
        <AppState icon="bi-grid-3x3-gap" :title="`L’application « ${MODULE_NAMES[module!] ?? module} » n’est pas installée`" text="Installez-la en un clic depuis le catalogue des applications. Vous pourrez la retirer à tout moment, vos données sont conservées.">
          <NuxtLink :to="`/dashboard/boutiques/${shop.id}/erp/applications`" class="btn btn-primary">
            <i class="bi bi-download me-1" aria-hidden="true" />Voir les applications
          </NuxtLink>
        </AppState>
      </div>
      <slot v-else />
    </template>
  </div>
</template>

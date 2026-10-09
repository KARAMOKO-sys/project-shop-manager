<script setup lang="ts">
import type { Moderation } from '~/types'

const props = defineProps<{ moderation?: Moderation | null, showScore?: boolean }>()

const meta = computed(() => {
  switch (props.moderation?.status) {
    case 'approved':
      return { label: 'Vérifié', variant: 'success', icon: 'bi-shield-check' }
    case 'rejected':
      return { label: 'Refusé', variant: 'danger', icon: 'bi-shield-x' }
    default:
      return { label: 'En vérification', variant: 'warning', icon: 'bi-hourglass-split' }
  }
})
</script>

<template>
  <span class="badge rounded-pill d-inline-flex align-items-center gap-1" :class="`text-bg-${meta.variant}`">
    <i class="bi" :class="meta.icon" aria-hidden="true" />
    {{ meta.label }}
    <span v-if="showScore && moderation" class="opacity-75 fw-normal">· risque {{ moderation.risk_score }}/100</span>
  </span>
</template>

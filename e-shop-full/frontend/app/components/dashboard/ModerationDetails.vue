<script setup lang="ts">
import type { Moderation } from '~/types'

const props = defineProps<{ moderation?: Moderation | null }>()

const riskVariant = computed(() => {
  const score = props.moderation?.risk_score ?? 0

  return score >= 70 ? 'danger' : score >= 30 ? 'warning' : 'success'
})
</script>

<template>
  <div v-if="moderation" class="d-grid gap-2">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <ModerationBadge :moderation="moderation" />
      <span class="small text-muted">
        Vérifié par {{ moderation.engine === 'claude' ? 'l’agent IA avancé' : 'l’agent IA local' }}
        <template v-if="moderation.checked_at"> · {{ formatDate(moderation.checked_at) }}</template>
      </span>
    </div>
    <div>
      <div class="d-flex justify-content-between small mb-1">
        <span class="fw-semibold">Score de risque</span>
        <span>{{ moderation.risk_score }}/100</span>
      </div>
      <div class="progress" role="progressbar" aria-label="Score de risque" :aria-valuenow="moderation.risk_score" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
        <div class="progress-bar" :class="`bg-${riskVariant}`" :style="{ width: `${moderation.risk_score}%` }" />
      </div>
    </div>
    <div v-if="moderation.reasons?.length">
      <p class="small fw-semibold mb-1">Motifs relevés :</p>
      <ul class="small mb-0 ps-3">
        <li v-for="reason in moderation.reasons" :key="reason">{{ reason }}</li>
      </ul>
    </div>
    <p v-else class="small text-muted mb-0">Aucun problème détecté.</p>
  </div>
  <p v-else class="small text-muted mb-0">Pas encore vérifié.</p>
</template>

<script setup lang="ts">
import type { Activity } from '~/types'

defineProps<{ activities: Activity[], emptyText?: string }>()
</script>

<template>
  <ul v-if="activities.length" class="list-unstyled erp-timeline mb-0">
    <li v-for="activity in activities" :key="activity.id" class="erp-timeline-item">
      <p class="mb-0 small">{{ activity.description }}</p>
      <p class="mb-0 text-muted" style="font-size: .75rem;">
        {{ formatDate(activity.created_at) }}<template v-if="activity.user"> · {{ activity.user }}</template>
      </p>
    </li>
  </ul>
  <p v-else class="small text-muted mb-0">{{ emptyText ?? 'Aucune activité pour le moment.' }}</p>
</template>

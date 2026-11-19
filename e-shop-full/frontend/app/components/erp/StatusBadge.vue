<script setup lang="ts">
defineProps<{ label: string, variant?: string }>()
</script>

<template>
  <span class="badge rounded-pill" :class="`text-bg-${variant ?? 'secondary'}`">{{ label }}</span>
</template>

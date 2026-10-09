<script setup lang="ts">
withDefaults(defineProps<{
  loading?: boolean
  icon?: string
  title?: string
  text?: string
  variant?: 'empty' | 'error'
}>(), {
  icon: 'bi-inbox',
  variant: 'empty',
})
</script>

<template>
  <div v-if="loading" class="text-center py-5" role="status">
    <div class="spinner-border text-primary" aria-hidden="true" />
    <p class="text-muted mt-3 mb-0">{{ text ?? 'Chargement…' }}</p>
  </div>
  <div v-else class="text-center py-5 px-3" :role="variant === 'error' ? 'alert' : undefined">
    <div class="icon-tile mb-3 mx-auto" :class="{ 'text-danger': variant === 'error' }" aria-hidden="true">
      <i class="bi" :class="variant === 'error' ? 'bi-wifi-off' : icon" />
    </div>
    <h2 v-if="title" class="h5 fw-bold">{{ title }}</h2>
    <p v-if="text" class="text-muted mb-3 mx-auto" style="max-width: 32rem;">{{ text }}</p>
    <slot />
  </div>
</template>

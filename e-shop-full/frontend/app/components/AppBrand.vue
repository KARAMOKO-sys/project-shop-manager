<script setup lang="ts">
const { config } = usePlatform()

/** "ShopAI" keeps its gold "AI" suffix; any other platform name is shown as is. */
const parts = computed(() => {
  const name = config.value.platform.name
  const match = /^(.+?)(AI|IA)$/.exec(name)

  return match ? { base: match[1], suffix: match[2] } : { base: name, suffix: '' }
})
</script>

<template>
  <span class="brand-mark" aria-hidden="true"><i class="bi bi-stars" /></span>
  <span>{{ parts.base }}<span v-if="parts.suffix" class="brand-ai">{{ parts.suffix }}</span></span>
</template>

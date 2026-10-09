<script setup lang="ts">
const props = withDefaults(defineProps<{
  src?: string | null
  alt: string
  color?: string
  ratio?: string
}>(), {
  ratio: '4 / 3',
})

const failed = ref(false)
const image = ref<HTMLImageElement | null>(null)

watch(() => props.src, () => {
  failed.value = false
})

onMounted(() => {
  if (image.value?.complete && image.value.naturalWidth === 0) {
    failed.value = true
  }
})
</script>

<template>
  <div class="product-media" :style="{ '--media-color': color ?? 'var(--app-primary)', aspectRatio: ratio }">
    <img
      v-if="src && !failed"
      ref="image"
      :src="src"
      :alt="alt"
      loading="lazy"
      @error="failed = true"
    >
    <div v-else class="product-media-placeholder" role="img" :aria-label="alt">
      <i class="bi bi-bag" aria-hidden="true" />
    </div>
  </div>
</template>

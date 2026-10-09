<script setup lang="ts">
const props = withDefaults(defineProps<{
  shopId: number | string
  id: string
  label?: string
  help?: string
}>(), {
  label: 'Téléverser une photo',
  help: 'JPEG, PNG ou WebP — 4 Mo maximum.',
})
const emit = defineEmits<{ uploaded: [url: string] }>()

const { uploading, error, upload } = useImageUpload(() => props.shopId)
const input = ref<HTMLInputElement | null>(null)

async function onChange(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) {
    return
  }

  const url = await upload(file)

  if (url) {
    emit('uploaded', url)
  }

  if (input.value) {
    input.value.value = ''
  }
}
</script>

<template>
  <div>
    <label :for="id" class="form-label">{{ label }}</label>
    <div class="d-flex align-items-center gap-2">
      <input
        :id="id"
        ref="input"
        type="file"
        class="form-control"
        :class="{ 'is-invalid': error }"
        accept="image/jpeg,image/png,image/webp"
        :disabled="uploading"
        :aria-describedby="`${id}-help ${id}-error`"
        @change="onChange"
      >
      <span v-if="uploading" class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status">
        <span class="visually-hidden">Téléversement en cours…</span>
      </span>
    </div>
    <div :id="`${id}-help`" class="form-text">{{ help }}</div>
    <FieldError :id="`${id}-error`" :message="error" />
  </div>
</template>

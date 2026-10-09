<script setup lang="ts">
const props = withDefaults(defineProps<{
  id: string
  placeholder?: string
  describedby?: string
  max?: number
}>(), {
  placeholder: 'Ajouter puis Entrée',
  max: 20,
})

const tags = defineModel<string[]>({ required: true })
const draft = ref('')
const input = ref<HTMLInputElement | null>(null)

function commit(): void {
  const value = draft.value.replace(/,/g, '').trim()

  if (value && !tags.value.some(tag => tag.toLowerCase() === value.toLowerCase()) && tags.value.length < props.max) {
    tags.value = [...tags.value, value]
  }

  draft.value = ''
}

function removeAt(index: number): void {
  tags.value = tags.value.filter((_, position) => position !== index)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault()
    commit()
  }
  else if (event.key === 'Backspace' && !draft.value && tags.value.length) {
    removeAt(tags.value.length - 1)
  }
}
</script>

<template>
  <div class="form-control tag-input" @click="input?.focus()">
    <span v-for="(tag, index) in tags" :key="tag" class="tag-chip">
      {{ tag }}
      <button type="button" :aria-label="`Retirer ${tag}`" @click.stop="removeAt(index)">
        <i class="bi bi-x" aria-hidden="true" />
      </button>
    </span>
    <input
      :id="id"
      ref="input"
      v-model="draft"
      type="text"
      :placeholder="tags.length ? '' : placeholder"
      :aria-describedby="describedby"
      @keydown="onKeydown"
      @blur="commit"
    >
  </div>
</template>

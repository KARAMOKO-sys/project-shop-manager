<script setup lang="ts">
/** Storefront product search: keeps a local draft and emits the trimmed term on submit. */
const props = withDefaults(defineProps<{ search: string, inputId?: string, compact?: boolean }>(), {
  inputId: 'shop-search',
})
const emit = defineEmits<{ search: [term: string] }>()

const draft = ref(props.search)

watch(() => props.search, (value) => {
  draft.value = value
})
</script>

<template>
  <form role="search" @submit.prevent="emit('search', draft.trim())">
    <label :for="inputId" class="visually-hidden">Rechercher un produit</label>
    <div class="input-group" :class="{ 'input-group-sm': compact }">
      <input :id="inputId" v-model="draft" type="search" class="form-control" placeholder="Rechercher un produit…">
      <button type="submit" class="btn btn-primary" aria-label="Rechercher"><i class="bi bi-search" aria-hidden="true" /></button>
    </div>
  </form>
</template>

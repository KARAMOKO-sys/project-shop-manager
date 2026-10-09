<script setup lang="ts">
/** Chips filtering a template gallery by page format ("all" shows everything). */
withDefaults(defineProps<{ label?: string, counts?: Record<string, number> }>(), {
  label: 'Filtrer par format',
})

const selected = defineModel<string>({ default: 'all' })

const options = computed(() => [{ value: 'all', label: 'Tous' }, ...SHOP_FORMATS.map(format => ({ value: format.value, label: format.label }))])
</script>

<template>
  <ul class="filter-chips" :aria-label="label">
    <li v-for="option in options" :key="option.value">
      <button
        type="button"
        class="filter-chip"
        :aria-pressed="selected === option.value"
        @click="selected = option.value"
      >
        {{ option.label }}<span v-if="counts && counts[option.value] !== undefined" class="ms-1 opacity-75">({{ counts[option.value] }})</span>
      </button>
    </li>
  </ul>
</template>

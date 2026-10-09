<script setup lang="ts">
const props = defineProps<{
  amount: number | string
  currency?: string
  compareAt?: number | string | null
}>()

const hasDiscount = computed(() => props.compareAt !== null && props.compareAt !== undefined && Number(props.compareAt) > Number(props.amount))
</script>

<template>
  <span class="price-tag">
    <span class="price-current">{{ formatPrice(amount, currency) }}</span>
    <template v-if="hasDiscount">
      <span class="visually-hidden">, au lieu de</span>
      <s class="price-compare">{{ formatPrice(compareAt, currency) }}</s>
    </template>
  </span>
</template>

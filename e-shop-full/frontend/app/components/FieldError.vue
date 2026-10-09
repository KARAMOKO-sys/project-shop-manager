<script setup lang="ts">
defineProps<{ id: string, message?: string }>()
</script>

<template>
  <div v-if="message" :id="id" class="invalid-feedback d-block">
    {{ message }}
  </div>
</template>

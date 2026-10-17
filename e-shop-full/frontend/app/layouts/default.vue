<script setup lang="ts">
const { config } = usePlatform()
</script>

<template>
  <div class="d-flex flex-column min-vh-100">
    <div v-if="config.platform.announcement" class="platform-announcement text-center small py-2 px-3" role="status">
      <i class="bi bi-megaphone me-1" aria-hidden="true" />{{ config.platform.announcement }}
    </div>
    <AppNavbar />
    <main id="contenu" class="flex-grow-1">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>

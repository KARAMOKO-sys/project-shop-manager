<script setup lang="ts">
useHead({
  titleTemplate: title => (title && !title.startsWith('ShopAI') ? `${title} · ShopAI` : 'ShopAI — Votre boutique en ligne propulsée par l’IA'),
})
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <a href="#contenu" class="skip-link">Aller au contenu</a>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <ToastHost />
  </div>
</template>

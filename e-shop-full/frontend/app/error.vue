<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

const isNotFound = computed(() => props.error.statusCode === 404)

useSeoMeta({ title: () => (isNotFound.value ? 'Page introuvable' : 'Erreur') })
</script>

<template>
  <NuxtLayout name="default">
    <section class="container py-5 text-center" style="max-width: 560px;">
      <div class="icon-tile mb-3 mx-auto" aria-hidden="true">
        <i class="bi" :class="isNotFound ? 'bi-signpost-split' : 'bi-exclamation-triangle'" />
      </div>
      <h1 class="h2">{{ isNotFound ? 'Page introuvable' : 'Une erreur est survenue' }}</h1>
      <p class="text-muted">
        {{ isNotFound ? 'La page que vous cherchez n’existe pas ou a été déplacée.' : 'Nous n’avons pas pu afficher cette page. Réessayez dans un instant.' }}
      </p>
      <button type="button" class="btn btn-primary" @click="clearError({ redirect: '/' })">
        Retour à l’accueil
      </button>
    </section>
  </NuxtLayout>
</template>

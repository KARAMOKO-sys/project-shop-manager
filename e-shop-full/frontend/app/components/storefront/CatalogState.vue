<script setup lang="ts">
/** Loading / error / empty states shared by every storefront format; renders the slot when products exist. */
defineProps<{ loading: boolean, failed: boolean, empty: boolean, filtered: boolean }>()
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <AppState v-if="loading" loading text="Chargement des produits…" />
  <AppState v-else-if="failed" variant="error" title="Impossible de charger les produits" text="Vérifiez votre connexion et réessayez.">
    <button type="button" class="btn btn-primary" @click="emit('retry')">Réessayer</button>
  </AppState>
  <AppState
    v-else-if="empty"
    icon="bi-search"
    :title="filtered ? 'Aucun produit ne correspond' : 'La boutique se prépare'"
    :text="filtered ? 'Essayez une autre recherche ou catégorie.' : 'Les premiers produits arrivent très bientôt.'"
  />
  <slot v-else />
</template>

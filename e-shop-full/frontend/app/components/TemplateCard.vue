<script setup lang="ts">
import type { Template } from '~/types'

const props = defineProps<{ template: Template, headingLevel?: 'h2' | 'h3' }>()

const format = computed(() => resolveFormat(props.template.theme?.format))
const formatIcon = computed(() => SHOP_FORMATS.find(option => option.value === format.value)?.icon ?? 'bi-grid')
</script>

<template>
  <article class="card template-card card-hover h-100 overflow-hidden">
    <div class="template-thumb-wrap">
      <TemplateThumbnail :theme="template.theme" :label="`Aperçu schématique du modèle ${template.name}`" />
    </div>
    <div class="card-body d-flex flex-column gap-2 p-4">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <component :is="headingLevel ?? 'h3'" class="h5 mb-0">{{ template.name }}</component>
        <span class="format-badge flex-shrink-0"><i class="bi" :class="formatIcon" aria-hidden="true" />{{ formatLabel(format) }}</span>
      </div>
      <p class="small text-muted mb-1">{{ template.description }}</p>
      <div v-if="template.ideal_for?.length" class="mb-2">
        <span class="small fw-semibold me-1">Idéal pour :</span>
        <span class="d-inline-flex flex-wrap gap-1 align-middle">
          <span v-for="item in template.ideal_for" :key="item" class="ideal-badge">{{ item }}</span>
        </span>
      </div>
      <NuxtLink
        :to="{ path: '/creer-ma-boutique', query: { modele: template.key } }"
        class="btn btn-outline-primary btn-sm align-self-start mt-auto"
        :aria-label="`Utiliser le modèle ${template.name}`"
      >
        Utiliser ce modèle <i class="bi bi-arrow-right ms-1" aria-hidden="true" />
      </NuxtLink>
    </div>
  </article>
</template>

<script setup lang="ts">
import type { Theme } from '~/types'

/** Mini storefront mock rendering the theme colors and the page structure of its format. */
const props = defineProps<{
  theme?: Partial<Theme> | null
  name?: string
  title?: string
  large?: boolean
}>()

const resolved = computed(() => resolveTheme(props.theme))
const style = computed(() => themeVars(props.theme))
const label = computed(() => props.title ?? props.name ?? 'Ma boutique')
const ariaLabel = computed(() => `Aperçu${props.name ? ` du modèle ${props.name}` : ''}, format ${formatLabel(resolved.value.format)}`)
</script>

<template>
  <div
    class="theme-preview"
    :class="[`tp-format-${resolved.format}`, { 'is-large': large }]"
    :style="style"
    role="img"
    :aria-label="ariaLabel"
  >
    <div class="tp-nav">
      <span class="tp-logo">{{ label }}</span>
      <i class="bi bi-bag" aria-hidden="true" />
    </div>

    <template v-if="resolved.format === 'editorial'">
      <div class="tp-editorial-hero">
        <span class="tp-title">{{ label }}</span>
        <span class="tp-line w-50" style="background: #fff; opacity: 0.6;" />
      </div>
      <div class="tp-underline-menu"><span class="is-active" /><span /><span /><span /></div>
      <div class="tp-story">
        <div class="tp-img" />
        <div class="tp-story-text"><span class="tp-line w-75" /><span class="tp-line w-50" /><span class="tp-btn is-solid">Lire</span></div>
      </div>
      <div class="tp-story is-reversed">
        <div class="tp-img" />
        <div class="tp-story-text"><span class="tp-line w-75" /><span class="tp-line w-50" /></div>
      </div>
    </template>

    <template v-else-if="resolved.format === 'catalog'">
      <div class="tp-searchbar" />
      <div class="tp-catalog">
        <div class="tp-sidebar">
          <span class="tp-line w-75" />
          <span class="tp-line" />
          <span class="tp-line w-50" />
          <span class="tp-range" />
        </div>
        <div class="tp-catalog-grid">
          <div v-for="index in 8" :key="index" class="tp-mini">
            <div class="tp-img" />
            <span class="tp-line w-75" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="resolved.format === 'menu'">
      <div class="text-center"><span class="tp-title">Notre carte</span></div>
      <div class="tp-tabs"><span class="is-active">Entrées</span><span>Plats</span><span>Boissons</span></div>
      <span class="tp-menu-section">Entrées</span>
      <div v-for="index in 3" :key="index" class="tp-menu-row">
        <span class="tp-line" />
        <span class="tp-dots" />
        <span class="tp-price" />
        <span class="tp-plus" />
      </div>
    </template>

    <template v-else-if="resolved.format === 'showcase'">
      <div class="tp-showcase-hero">
        <span class="tp-kicker">Collection</span>
        <span class="tp-title">{{ label }}</span>
        <span class="tp-rule" />
      </div>
      <div class="tp-showcase-grid">
        <div class="tp-img" />
        <div class="tp-img" />
      </div>
    </template>

    <template v-else-if="resolved.format === 'single_product'">
      <div class="tp-single">
        <div class="tp-img" />
        <div class="tp-single-info">
          <span class="tp-title">{{ label }}</span>
          <span class="tp-line w-50" />
          <span class="tp-btn is-solid">Commander</span>
        </div>
      </div>
      <div class="tp-badges"><span /><span /><span /></div>
    </template>

    <template v-else>
      <div class="tp-hero" :class="`tp-hero-${resolved.hero_style}`">
        <span class="tp-title">{{ label }}</span>
        <span class="tp-line w-50" />
        <span class="tp-btn">Découvrir</span>
      </div>
      <div class="tp-pills"><span class="is-active" /><span /><span /></div>
      <div class="tp-products" :class="`tp-layout-${resolved.layout}`">
        <div v-for="index in 3" :key="index" class="tp-product">
          <div class="tp-img" />
          <span class="tp-line w-75" />
        </div>
      </div>
    </template>
  </div>
</template>

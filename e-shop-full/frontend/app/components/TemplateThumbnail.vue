<script setup lang="ts">
import type { Theme } from '~/types'

/**
 * Wireframe schematic of a storefront format, drawn with the given theme colors.
 * Purely decorative structure: exposed to assistive tech as a single labelled image.
 */
const props = defineProps<{
  theme?: Partial<Theme> | null
  format?: string | null
  label?: string
}>()

const uid = useId()
const gradientId = computed(() => `tt-gradient-${uid}`)

const resolved = computed(() => resolveTheme({ ...(props.theme ?? {}), ...(props.format ? { format: props.format as Theme['format'] } : {}) }))
const format = computed(() => resolved.value.format)
const colors = computed(() => ({
  primary: resolved.value.primary,
  secondary: resolved.value.secondary,
  background: resolved.value.background,
  surface: resolved.value.surface,
  text: resolved.value.text,
  onPrimary: contrastText(resolved.value.primary),
}))
const rx = computed(() => Math.min(6, radiusToPx(resolved.value.radius) / 3))
const fill = computed(() => `url(#${gradientId.value})`)
const ariaLabel = computed(() => props.label ?? `Structure de page « ${formatLabel(format.value)} »`)

const GRID_CLASSIC = [0, 1, 2].flatMap(column => [0, 1].map(row => ({ x: 6 + column * 50, y: 60 + row * 25 })))
const GRID_CATALOG = [0, 1, 2, 3].flatMap(column => [0, 1, 2].map(row => ({ x: 41 + column * 28.5, y: 27 + row * 26 })))
const MENU_ROWS = [56, 68, 80, 92]
</script>

<template>
  <svg class="template-thumb" viewBox="0 0 160 110" role="img" :aria-label="ariaLabel" preserveAspectRatio="xMidYMid meet">
    <defs>
      <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" :stop-color="colors.primary" />
        <stop offset="1" :stop-color="colors.secondary" />
      </linearGradient>
    </defs>
    <rect width="160" height="110" :fill="colors.background" />
    <rect width="160" height="12" :fill="colors.surface" />
    <circle cx="9" cy="6" r="3" :fill="colors.primary" />
    <rect x="15" y="4.5" width="22" height="3" rx="1.5" :fill="colors.text" opacity="0.7" />
    <rect x="142" y="3.5" width="10" height="5" rx="2.5" :fill="colors.primary" />

    <g v-if="format === 'editorial'">
      <rect x="0" y="12" width="160" height="38" :fill="fill" />
      <rect x="0" y="12" width="160" height="38" fill="#000" opacity="0.18" />
      <rect x="12" y="29" width="84" height="6" rx="1" fill="#fff" opacity="0.95" />
      <rect x="12" y="39" width="52" height="3" rx="1.5" fill="#fff" opacity="0.7" />
      <rect x="12" y="55" width="16" height="2.5" rx="1.25" :fill="colors.primary" />
      <rect x="12" y="59.5" width="16" height="1" :fill="colors.primary" />
      <rect x="34" y="55" width="16" height="2.5" rx="1.25" :fill="colors.text" opacity="0.3" />
      <rect x="56" y="55" width="16" height="2.5" rx="1.25" :fill="colors.text" opacity="0.3" />
      <rect x="12" y="60.5" width="136" height="0.6" :fill="colors.text" opacity="0.15" />
      <rect x="12" y="65" width="70" height="19" :rx="rx" :fill="fill" opacity="0.75" />
      <rect x="90" y="68" width="50" height="3.5" rx="1.5" :fill="colors.text" opacity="0.75" />
      <rect x="90" y="74" width="44" height="2" rx="1" :fill="colors.text" opacity="0.25" />
      <rect x="90" y="78" width="34" height="2" rx="1" :fill="colors.text" opacity="0.25" />
      <rect x="78" y="88" width="70" height="19" :rx="rx" :fill="fill" opacity="0.75" />
      <rect x="12" y="91" width="50" height="3.5" rx="1.5" :fill="colors.text" opacity="0.75" />
      <rect x="12" y="97" width="44" height="2" rx="1" :fill="colors.text" opacity="0.25" />
      <rect x="12" y="101" width="34" height="2" rx="1" :fill="colors.text" opacity="0.25" />
    </g>

    <g v-else-if="format === 'catalog'">
      <rect x="40" y="16" width="80" height="6" rx="3" :fill="colors.surface" :stroke="colors.text" stroke-opacity="0.15" stroke-width="0.6" />
      <rect x="6" y="27" width="31" height="77" :rx="rx" :fill="colors.surface" />
      <rect x="10" y="31" width="16" height="2" rx="1" :fill="colors.text" opacity="0.45" />
      <rect x="10" y="37" width="22" height="3" rx="1.5" :fill="colors.primary" />
      <rect v-for="index in 4" :key="`cat-${index}`" x="10" :y="42 + index * 5" width="20" height="2" rx="1" :fill="colors.text" opacity="0.25" />
      <rect x="10" y="72" width="16" height="2" rx="1" :fill="colors.text" opacity="0.45" />
      <rect x="10" y="78" width="23" height="1.5" rx="0.75" :fill="colors.text" opacity="0.2" />
      <rect x="14" y="78" width="13" height="1.5" :fill="colors.primary" />
      <rect x="10" y="86" width="6" height="3.5" rx="1.75" :fill="colors.primary" />
      <rect x="18" y="86.5" width="12" height="2" rx="1" :fill="colors.text" opacity="0.25" />
      <g v-for="(card, index) in GRID_CATALOG" :key="`card-${index}`">
        <rect :x="card.x" :y="card.y" width="26" height="24" :rx="rx * 0.6" :fill="colors.surface" />
        <rect :x="card.x + 1.5" :y="card.y + 1.5" width="23" height="13" :rx="rx * 0.5" :fill="fill" opacity="0.6" />
        <rect :x="card.x + 1.5" :y="card.y + 17" width="18" height="1.8" rx="0.9" :fill="colors.text" opacity="0.35" />
        <rect :x="card.x + 1.5" :y="card.y + 20.5" width="10" height="1.8" rx="0.9" :fill="colors.primary" />
      </g>
    </g>

    <g v-else-if="format === 'menu'">
      <rect x="55" y="17" width="50" height="5" rx="1" :fill="colors.text" opacity="0.8" />
      <rect x="72" y="26" width="16" height="1" :fill="colors.secondary" />
      <rect x="12" y="32" width="26" height="7" rx="3.5" :fill="colors.primary" />
      <rect x="42" y="32" width="26" height="7" rx="3.5" :fill="colors.text" opacity="0.1" />
      <rect x="72" y="32" width="26" height="7" rx="3.5" :fill="colors.text" opacity="0.1" />
      <rect x="102" y="32" width="26" height="7" rx="3.5" :fill="colors.text" opacity="0.1" />
      <rect x="12" y="44" width="136" height="0.6" :fill="colors.text" opacity="0.15" />
      <rect x="12" y="48" width="32" height="3" rx="1.5" :fill="colors.primary" />
      <g v-for="row in MENU_ROWS" :key="`row-${row}`">
        <rect x="12" :y="row" width="34" height="3" rx="1.5" :fill="colors.text" opacity="0.75" />
        <line x1="50" :y1="row + 2" x2="112" :y2="row + 2" :stroke="colors.text" stroke-opacity="0.35" stroke-width="0.8" stroke-dasharray="1 2" />
        <rect x="12" :y="row + 5" width="56" height="2" rx="1" :fill="colors.text" opacity="0.22" />
        <rect x="116" :y="row" width="16" height="3" rx="1.5" :fill="colors.text" opacity="0.75" />
        <circle cx="145" :cy="row + 2" r="4" :fill="colors.primary" />
        <path :d="`M143 ${row + 2}h4M145 ${row}v4`" :stroke="colors.onPrimary" stroke-width="0.9" />
      </g>
    </g>

    <g v-else-if="format === 'showcase'">
      <rect x="66" y="18" width="28" height="1.6" rx="0.8" :fill="colors.text" opacity="0.4" />
      <rect x="44" y="23" width="72" height="6" rx="1" :fill="colors.text" opacity="0.78" />
      <rect x="72" y="34" width="16" height="0.8" :fill="colors.secondary" />
      <rect x="14" y="41" width="62" height="54" :rx="rx" :fill="fill" opacity="0.8" />
      <rect x="84" y="41" width="62" height="54" :rx="rx" :fill="fill" opacity="0.8" />
      <rect x="30" y="99" width="30" height="2" rx="1" :fill="colors.text" opacity="0.4" />
      <rect x="100" y="99" width="30" height="2" rx="1" :fill="colors.text" opacity="0.4" />
      <rect x="38" y="104" width="14" height="1.6" rx="0.8" :fill="colors.secondary" />
      <rect x="108" y="104" width="14" height="1.6" rx="0.8" :fill="colors.secondary" />
    </g>

    <g v-else-if="format === 'single_product'">
      <rect x="8" y="18" width="70" height="64" :rx="rx" :fill="fill" opacity="0.85" />
      <rect x="86" y="21" width="22" height="2" rx="1" :fill="colors.secondary" />
      <rect x="86" y="27" width="62" height="6" rx="1" :fill="colors.text" opacity="0.8" />
      <rect x="86" y="36" width="44" height="6" rx="1" :fill="colors.text" opacity="0.8" />
      <rect x="86" y="47" width="30" height="4" rx="2" :fill="colors.primary" />
      <rect x="86" y="56" width="18" height="9" rx="4.5" fill="none" :stroke="colors.text" stroke-opacity="0.3" stroke-width="0.7" />
      <rect x="108" y="56" width="44" height="9" rx="4.5" :fill="colors.primary" />
      <rect x="116" y="59.5" width="28" height="2" rx="1" :fill="colors.onPrimary" opacity="0.9" />
      <g v-for="index in 2" :key="`benefit-${index}`">
        <circle cx="88" :cy="68 + index * 5" r="1.5" :fill="colors.primary" />
        <rect x="92" :y="67 + index * 5" width="40" height="2" rx="1" :fill="colors.text" opacity="0.3" />
      </g>
      <g v-for="index in 3" :key="`badge-${index}`">
        <rect :x="8 + (index - 1) * 49" y="88" width="45" height="16" :rx="rx" :fill="colors.surface" :stroke="colors.text" stroke-opacity="0.1" stroke-width="0.6" />
        <circle :cx="16 + (index - 1) * 49" cy="96" r="3" :fill="colors.secondary" opacity="0.8" />
        <rect :x="22 + (index - 1) * 49" y="95" width="24" height="2" rx="1" :fill="colors.text" opacity="0.35" />
      </g>
    </g>

    <g v-else>
      <rect x="6" y="17" width="148" height="31" :rx="rx" :fill="resolved.hero_style === 'minimal' ? colors.surface : fill" />
      <rect x="14" y="25" width="56" height="4.5" rx="1" :fill="resolved.hero_style === 'minimal' ? colors.text : colors.onPrimary" opacity="0.9" />
      <rect x="14" y="32" width="38" height="2.5" rx="1.25" :fill="resolved.hero_style === 'minimal' ? colors.text : colors.onPrimary" opacity="0.55" />
      <rect x="14" y="38" width="22" height="5" rx="2.5" :fill="resolved.hero_style === 'minimal' ? colors.primary : colors.surface" />
      <rect x="6" y="52" width="16" height="4" rx="2" :fill="colors.primary" />
      <rect x="25" y="52" width="16" height="4" rx="2" :fill="colors.text" opacity="0.15" />
      <rect x="44" y="52" width="16" height="4" rx="2" :fill="colors.text" opacity="0.15" />
      <g v-for="(card, index) in GRID_CLASSIC" :key="`classic-${index}`">
        <rect :x="card.x" :y="card.y" width="46" height="22" :rx="rx * 0.7" :fill="colors.surface" />
        <rect :x="card.x + 2" :y="card.y + 2" width="42" height="12" :rx="rx * 0.5" :fill="fill" opacity="0.55" />
        <rect :x="card.x + 2" :y="card.y + 17" width="24" height="2" rx="1" :fill="colors.text" opacity="0.35" />
        <rect :x="card.x + 32" :y="card.y + 17" width="12" height="2" rx="1" :fill="colors.primary" />
      </g>
    </g>
  </svg>
</template>

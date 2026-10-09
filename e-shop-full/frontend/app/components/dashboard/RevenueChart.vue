<script setup lang="ts">
import type { RevenueDay } from '~/types'

const props = defineProps<{ days: RevenueDay[], currency: string }>()

const WIDTH = 640
const HEIGHT = 200
const PLOT_TOP = 22
const PLOT_BOTTOM = 172
const PLOT_LEFT = 4
const PLOT_RIGHT = WIDTH - 4
const GAP = 2
const RADIUS = 4

const hovered = ref<number | null>(null)
const uid = useId()

const maxRevenue = computed(() => Math.max(0, ...props.days.map(day => Number(day.revenue))))
const totalRevenue = computed(() => props.days.reduce((sum, day) => sum + Number(day.revenue), 0))
const totalOrders = computed(() => props.days.reduce((sum, day) => sum + Number(day.orders), 0))

/** Round the scale up to a "nice" value so the top gridline reads cleanly. */
const scaleMax = computed(() => {
  const max = maxRevenue.value

  if (max <= 0) {
    return 1
  }

  const magnitude = 10 ** Math.floor(Math.log10(max))

  return Math.ceil(max / magnitude) * magnitude
})

const slot = computed(() => (PLOT_RIGHT - PLOT_LEFT) / Math.max(1, props.days.length))

function dayLabel(date: string): string {
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(`${date}T00:00:00`))
}

/** Bar path with rounded top corners only, anchored to the baseline. */
function barPath(x: number, width: number, height: number): string {
  const r = Math.min(RADIUS, width / 2, height)
  const y = PLOT_BOTTOM - height

  return `M${x},${PLOT_BOTTOM}V${y + r}Q${x},${y} ${x + r},${y}H${x + width - r}Q${x + width},${y} ${x + width},${y + r}V${PLOT_BOTTOM}Z`
}

const bars = computed(() => props.days.map((day, index) => {
  const revenue = Number(day.revenue)
  const x = PLOT_LEFT + index * slot.value + GAP / 2
  const width = Math.max(1, slot.value - GAP)
  const height = revenue > 0 ? Math.max(2, (revenue / scaleMax.value) * (PLOT_BOTTOM - PLOT_TOP)) : 0

  return {
    ...day,
    index,
    x,
    width,
    height,
    path: height > 0 ? barPath(x, width, height) : '',
    label: `${dayLabel(day.date)} : ${formatPrice(revenue, props.currency)}, ${formatNumber(day.orders)} commande(s)`,
  }
}))

const axisLabels = computed(() => {
  const count = bars.value.length

  if (!count) {
    return []
  }

  const indexes = Array.from(new Set([0, Math.floor((count - 1) / 2), count - 1]))

  return indexes.map(index => ({
    index,
    x: bars.value[index]!.x + bars.value[index]!.width / 2,
    text: dayLabel(bars.value[index]!.date),
    anchor: index === 0 ? 'start' : index === count - 1 ? 'end' : 'middle',
  }))
})

/** Index of the best day, highlighted in gold. */
const peakIndex = computed(() => (maxRevenue.value > 0 ? bars.value.findIndex(bar => Number(bar.revenue) === maxRevenue.value) : -1))

const hoveredBar = computed(() => (hovered.value === null ? null : bars.value[hovered.value] ?? null))

const summary = computed(() => `Chiffre d’affaires des ${props.days.length} derniers jours : ${formatPrice(totalRevenue.value, props.currency)} pour ${formatNumber(totalOrders.value)} commande(s). Maximum journalier : ${formatPrice(maxRevenue.value, props.currency)}.`)
</script>

<template>
  <figure class="revenue-chart mb-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
      <figcaption>
        <h2 :id="`${uid}-title`" class="h5 fw-bold mb-0">Chiffre d’affaires — {{ days.length }} derniers jours</h2>
        <p class="small text-muted mb-0">
          Total : <PriceTag :amount="totalRevenue" :currency="currency" /> · {{ formatNumber(totalOrders) }} commande(s)
        </p>
      </figcaption>
      <p class="revenue-chart-readout small mb-0 text-end" aria-hidden="true">
        <template v-if="hoveredBar">
          <span class="fw-semibold">{{ dayLabel(hoveredBar.date) }}</span><br>
          {{ formatPrice(hoveredBar.revenue, currency) }} · {{ formatNumber(hoveredBar.orders) }} cmd
        </template>
        <span v-else class="text-muted"><span class="revenue-chart-legend me-1" />Meilleur jour · survolez une barre pour le détail</span>
      </p>
    </div>

    <svg
      class="revenue-chart-svg"
      :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
      role="img"
      :aria-label="summary"
      @mouseleave="hovered = null"
    >
      <title>{{ summary }}</title>
      <g class="revenue-chart-grid" aria-hidden="true">
        <line :x1="PLOT_LEFT" :x2="PLOT_RIGHT" :y1="PLOT_TOP" :y2="PLOT_TOP" />
        <line :x1="PLOT_LEFT" :x2="PLOT_RIGHT" :y1="(PLOT_TOP + PLOT_BOTTOM) / 2" :y2="(PLOT_TOP + PLOT_BOTTOM) / 2" />
        <text :x="PLOT_LEFT" :y="PLOT_TOP - 6">{{ formatPrice(scaleMax, currency) }}</text>
        <line class="is-baseline" :x1="PLOT_LEFT" :x2="PLOT_RIGHT" :y1="PLOT_BOTTOM" :y2="PLOT_BOTTOM" />
      </g>
      <g v-for="bar in bars" :key="bar.date" @mouseenter="hovered = bar.index">
        <rect class="revenue-chart-hit" :x="PLOT_LEFT + bar.index * slot" :y="PLOT_TOP" :width="slot" :height="PLOT_BOTTOM - PLOT_TOP" />
        <path v-if="bar.path" class="revenue-chart-bar" :class="{ 'is-active': hovered === bar.index, 'is-peak': bar.index === peakIndex }" :d="bar.path">
          <title>{{ bar.label }}</title>
        </path>
      </g>
      <g class="revenue-chart-axis" aria-hidden="true">
        <text v-for="label in axisLabels" :key="label.index" :x="label.x" :y="HEIGHT - 6" :text-anchor="label.anchor">{{ label.text }}</text>
      </g>
    </svg>

    <table class="visually-hidden">
      <caption>Chiffre d’affaires et commandes par jour</caption>
      <thead>
        <tr>
          <th scope="col">Date</th>
          <th scope="col">Chiffre d’affaires</th>
          <th scope="col">Commandes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="day in days" :key="day.date">
          <th scope="row">{{ formatShortDate(day.date) }}</th>
          <td>{{ formatPrice(day.revenue, currency) }}</td>
          <td>{{ formatNumber(day.orders) }}</td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>

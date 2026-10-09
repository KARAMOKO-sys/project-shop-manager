<script setup lang="ts">
import type { PaginationMeta } from '~/types'

const props = defineProps<{ meta?: PaginationMeta | null }>()
const page = defineModel<number>('page', { required: true })

const pages = computed(() => {
  const last = props.meta?.last_page ?? 1
  const start = Math.max(1, page.value - 2)
  const end = Math.min(last, start + 4)

  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
</script>

<template>
  <nav v-if="meta && meta.last_page > 1" aria-label="Pagination" class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
      <li class="page-item" :class="{ disabled: page <= 1 }">
        <button type="button" class="page-link" :disabled="page <= 1" aria-label="Page précédente" @click="page--">
          <i class="bi bi-chevron-left" aria-hidden="true" />
        </button>
      </li>
      <li v-for="number in pages" :key="number" class="page-item" :class="{ active: number === page }">
        <button
          type="button"
          class="page-link"
          :aria-current="number === page ? 'page' : undefined"
          @click="page = number"
        >
          {{ number }}
        </button>
      </li>
      <li class="page-item" :class="{ disabled: page >= meta.last_page }">
        <button type="button" class="page-link" :disabled="page >= meta.last_page" aria-label="Page suivante" @click="page++">
          <i class="bi bi-chevron-right" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </nav>
</template>

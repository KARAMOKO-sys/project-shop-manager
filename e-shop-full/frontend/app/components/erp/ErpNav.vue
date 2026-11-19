<script setup lang="ts">
import type { Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const route = useRoute()

const basePath = computed(() => `/dashboard/boutiques/${props.shop.id}/erp`)
const links = computed(() => ERP_NAV.filter(link => !link.module || (props.shop.modules ?? []).includes(link.module)))

function isActive(path: string): boolean {
  const target = `${basePath.value}${path}`

  return path === '' ? route.path.replace(/\/$/, '') === target : route.path.startsWith(target)
}
</script>

<template>
  <div class="mb-4 d-print-none">
    <nav aria-label="Fil d’Ariane">
      <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><NuxtLink to="/dashboard">Mes boutiques</NuxtLink></li>
        <li class="breadcrumb-item"><NuxtLink :to="`/dashboard/boutiques/${shop.id}`">{{ shop.name }}</NuxtLink></li>
        <li class="breadcrumb-item active" aria-current="page">Gestion d’entreprise</li>
      </ol>
    </nav>
    <ul class="nav nav-pills erp-nav flex-nowrap overflow-auto gap-1 pb-1" aria-label="Applications de gestion">
      <li v-for="link in links" :key="link.path" class="nav-item">
        <NuxtLink
          :to="`${basePath}${link.path}`"
          class="nav-link text-nowrap"
          :class="{ active: isActive(link.path) }"
          :aria-current="isActive(link.path) ? 'page' : undefined"
        >
          <i class="bi me-1" :class="link.icon" aria-hidden="true" />{{ link.label }}
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
const { toasts, dismiss } = useToast()

const icons: Record<string, string> = {
  success: 'bi-check-circle-fill',
  danger: 'bi-x-octagon-fill',
  warning: 'bi-exclamation-triangle-fill',
  info: 'bi-info-circle-fill',
}
</script>

<template>
  <div class="toast-host" aria-live="polite" aria-atomic="false">
    <div
      v-for="toast in toasts"
      :key="toast.id"
      class="toast show align-items-center border-0 shadow"
      :class="`text-bg-${toast.variant}`"
      :role="toast.variant === 'danger' ? 'alert' : 'status'"
    >
      <div class="d-flex">
        <div class="toast-body d-flex gap-2 align-items-start">
          <i class="bi mt-1" :class="icons[toast.variant]" aria-hidden="true" />
          <span>{{ toast.message }}</span>
        </div>
        <button
          type="button"
          class="btn-close me-2 m-auto"
          :class="{ 'btn-close-white': toast.variant !== 'warning' }"
          aria-label="Fermer la notification"
          @click="dismiss(toast.id)"
        />
      </div>
    </div>
  </div>
</template>

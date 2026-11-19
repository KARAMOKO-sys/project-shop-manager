<script setup lang="ts">
/** Lightweight accessible dialog driven by Vue state (no Bootstrap JS needed). */
const props = withDefaults(defineProps<{ title: string, size?: 'sm' | 'lg' | 'xl' | null }>(), { size: null })
const open = defineModel<boolean>('open', { required: true })

const dialog = ref<HTMLElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null

function close(): void {
  open.value = false
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

watch(open, (value) => {
  if (!import.meta.client) {
    return
  }

  if (value) {
    previousFocus = document.activeElement as HTMLElement | null
    document.body.classList.add('modal-open')
    nextTick(() => {
      const target = dialog.value?.querySelector<HTMLElement>('input, select, textarea, button:not(.btn-close)')
      ;(target ?? dialog.value)?.focus()
    })
  }
  else {
    document.body.classList.remove('modal-open')
    previousFocus?.focus()
  }
}, { immediate: true })

onBeforeUnmount(() => {
  if (import.meta.client) {
    document.body.classList.remove('modal-open')
  }
})
</script>

<template>
  <Teleport to="body">
    <template v-if="open">
      <div class="modal d-block" tabindex="-1" role="presentation" @keydown="onKeydown" @mousedown.self="close">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down" :class="props.size ? `modal-${props.size}` : ''">
          <div ref="dialog" class="modal-content" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1">
            <div class="modal-header">
              <h2 :id="titleId" class="modal-title h5 fw-bold">{{ title }}</h2>
              <button type="button" class="btn-close" aria-label="Fermer" @click="close" />
            </div>
            <div class="modal-body">
              <slot />
            </div>
            <div v-if="$slots.footer" class="modal-footer">
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop show" aria-hidden="true" />
    </template>
  </Teleport>
</template>

<script setup lang="ts">
import type { ChatReply, ChatTurn, Product, Resource } from '~/types'

interface ChatMessage extends ChatTurn {
  products?: Product[]
}

const props = withDefaults(defineProps<{
  endpoint: string
  title?: string
  subtitle?: string
  welcome?: string
  suggestions?: string[]
  shopSlug?: string
}>(), {
  title: 'Assistant ShopAI',
  subtitle: 'Répond en quelques secondes, 24h/24',
  welcome: 'Bonjour ! Je suis votre assistant. Comment puis-je vous aider ?',
  suggestions: () => [],
})

const api = useApi()
const panelId = useId()
const inputId = useId()

const open = ref(false)
const sending = ref(false)
const draft = ref('')
const messages = ref<ChatMessage[]>([{ role: 'assistant', content: props.welcome }])
const currentSuggestions = ref<string[]>([...props.suggestions])
const scroller = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)

async function scrollToBottom(): Promise<void> {
  await nextTick()
  scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: 'smooth' })
}

async function toggle(): Promise<void> {
  open.value = !open.value

  if (open.value) {
    await nextTick()
    input.value?.focus()
    await scrollToBottom()
  }
}

async function send(text: string): Promise<void> {
  const message = text.trim()

  if (!message || sending.value) {
    return
  }

  const history: ChatTurn[] = messages.value.slice(1).map(({ role, content }) => ({ role, content }))
  messages.value.push({ role: 'user', content: message })
  draft.value = ''
  currentSuggestions.value = []
  sending.value = true
  await scrollToBottom()

  try {
    const response = await api<Resource<ChatReply>>(props.endpoint, { method: 'POST', body: { message, history } })
    messages.value.push({ role: 'assistant', content: response.data.reply, products: response.data.products ?? [] })
    currentSuggestions.value = response.data.suggestions ?? []
  }
  catch {
    messages.value.push({ role: 'assistant', content: 'Désolé, je ne peux pas répondre pour le moment. Réessayez dans un instant.' })
  }
  finally {
    sending.value = false
    await scrollToBottom()
  }
}

function productLink(product: Product): string {
  return `/boutique/${props.shopSlug ?? product.shop?.slug ?? ''}/produit/${product.slug}`
}
</script>

<template>
  <div>
    <div
      v-if="open"
      :id="panelId"
      class="chat-panel"
      role="dialog"
      :aria-label="title"
      @keydown.esc="toggle"
    >
      <div class="chat-header d-flex align-items-center gap-2">
        <span class="icon-tile bg-white bg-opacity-25 text-white" style="width: 2.4rem; height: 2.4rem; font-size: 1.1rem;" aria-hidden="true">
          <i class="bi bi-stars" />
        </span>
        <div class="flex-grow-1 lh-sm">
          <p class="fw-bold mb-0">{{ title }}</p>
          <p class="small mb-0 opacity-75">{{ subtitle }}</p>
        </div>
        <button type="button" class="btn-close btn-close-white" aria-label="Fermer l’assistant" @click="toggle" />
      </div>

      <div ref="scroller" class="chat-messages" aria-live="polite">
        <template v-for="(message, index) in messages" :key="index">
          <div class="chat-bubble" :class="message.role === 'user' ? 'is-user' : 'is-assistant'">
            <span class="visually-hidden">{{ message.role === 'user' ? 'Vous :' : 'Assistant :' }}</span>
            {{ message.content }}
          </div>
          <div v-if="message.products?.length" class="chat-products">
            <NuxtLink v-for="product in message.products" :key="product.id" :to="productLink(product)" class="chat-product">
              <ProductImage :src="product.image_url" :alt="product.name" ratio="4 / 3" />
              <div class="p-2">
                <p class="fw-semibold mb-1 text-truncate">{{ product.name }}</p>
                <PriceTag :amount="product.price" :currency="product.currency" />
              </div>
            </NuxtLink>
          </div>
        </template>
        <div v-if="sending" class="chat-bubble is-assistant chat-typing" role="status">
          <span /><span /><span />
          <span class="visually-hidden">L’assistant écrit…</span>
        </div>
      </div>

      <div v-if="currentSuggestions.length" class="chat-suggestions">
        <button
          v-for="suggestion in currentSuggestions"
          :key="suggestion"
          type="button"
          class="btn btn-sm btn-outline-primary rounded-pill"
          :disabled="sending"
          @click="send(suggestion)"
        >
          {{ suggestion }}
        </button>
      </div>

      <form class="d-flex gap-2 p-3 bg-white border-top" @submit.prevent="send(draft)">
        <label :for="inputId" class="visually-hidden">Votre message</label>
        <input
          :id="inputId"
          ref="input"
          v-model="draft"
          type="text"
          class="form-control rounded-pill"
          placeholder="Écrivez votre message…"
          autocomplete="off"
          maxlength="1000"
        >
        <button type="submit" class="btn btn-primary rounded-circle flex-shrink-0" style="width: 2.6rem; height: 2.6rem;" :disabled="sending || !draft.trim()" aria-label="Envoyer">
          <i class="bi bi-send-fill" aria-hidden="true" />
        </button>
      </form>
    </div>

    <button
      type="button"
      class="chat-launcher"
      :aria-expanded="open"
      :aria-controls="panelId"
      :aria-label="open ? 'Fermer l’assistant' : `Ouvrir ${title}`"
      @click="toggle"
    >
      <i class="bi" :class="open ? 'bi-x-lg' : 'bi-chat-dots-fill'" aria-hidden="true" />
    </button>
  </div>
</template>

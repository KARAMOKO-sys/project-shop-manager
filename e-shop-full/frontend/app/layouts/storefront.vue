<script setup lang="ts">
const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))

const { data: shop } = await useStorefrontShop(slug)
const { count } = useCart(slug.value)
const { config, features } = usePlatform()

const theme = computed(() => resolveTheme(shop.value?.theme))
const style = computed(() => themeVars(shop.value?.theme))
const fontUrl = computed(() => googleFontUrl(theme.value.font))

useHead(() => ({
  link: fontUrl.value ? [{ rel: 'stylesheet', href: fontUrl.value, key: 'shop-font' }] : [],
  meta: [{ name: 'theme-color', content: theme.value.primary, key: 'theme-color' }],
}))

useCloseOnNavigate('#cartOffcanvas', 'Offcanvas')
</script>

<template>
  <div class="shop-themed" :style="style">
    <nav class="navbar shop-navbar sticky-top py-3" aria-label="Navigation de la boutique">
      <div class="container flex-nowrap gap-2">
        <NuxtLink :to="`/boutique/${slug}`" class="navbar-brand d-flex align-items-center gap-2 fw-bold text-reset min-w-0 me-0">
          <span class="shop-logo-mark" aria-hidden="true">
            <img v-if="shop?.logo_url" :src="shop.logo_url" alt="">
            <template v-else>{{ (shop?.name ?? 'B').charAt(0).toUpperCase() }}</template>
          </span>
          <span class="text-truncate">{{ shop?.name ?? 'Boutique' }}</span>
        </NuxtLink>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <NuxtLink :to="`/boutique/${slug}/suivi`" class="btn btn-light" title="Suivre ma commande">
            <i class="bi bi-truck" aria-hidden="true" />
            <span class="d-none d-md-inline ms-1">Suivi de commande</span>
            <span class="visually-hidden d-md-none">Suivre ma commande</span>
          </NuxtLink>
          <button
            type="button"
            class="btn btn-primary position-relative"
            data-bs-toggle="offcanvas"
            data-bs-target="#cartOffcanvas"
            aria-controls="cartOffcanvas"
          >
            <i class="bi bi-bag" aria-hidden="true" />
            <span class="d-none d-sm-inline ms-1">Panier</span>
            <span class="visually-hidden">, {{ count }} article(s)</span>
            <span
              v-if="count"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"
              aria-hidden="true"
            >{{ count }}</span>
          </button>
        </div>
      </div>
    </nav>

    <main id="contenu" class="flex-grow-1">
      <slot />
    </main>

    <footer class="py-4 mt-5 border-top small text-center text-muted">
      <div class="container">
        <NuxtLink :to="`/boutique/${slug}/suivi`" class="d-inline-block mb-2"><i class="bi bi-truck me-1" aria-hidden="true" />Suivre ma commande</NuxtLink>
        <br>
        © {{ shop?.name }} · Propulsé par
        <NuxtLink to="/" class="fw-semibold">{{ config.platform.name }}</NuxtLink>
      </div>
    </footer>

    <StorefrontCartOffcanvas :shop-slug="slug" :currency="shop?.currency ?? 'XOF'" :color="theme.primary" />

    <ChatWidget
      v-if="shop && features.shop_chat"
      :key="slug"
      :endpoint="`/storefront/${slug}/chat`"
      :shop-slug="slug"
      :title="`Assistant ${shop.name}`"
      subtitle="Questions, conseils, suivi de commande"
      :welcome="`Bienvenue chez ${shop.name} ! Je peux vous aider à trouver un produit ou répondre à vos questions.`"
      :suggestions="['Quels sont vos best-sellers ?', 'Livrez-vous à domicile ?', 'Puis-je payer en Mobile Money ?']"
    />
  </div>
</template>

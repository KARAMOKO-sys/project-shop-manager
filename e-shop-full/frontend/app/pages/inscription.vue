<script setup lang="ts">
definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Inscription', description: 'Créez votre compte ShopAI gratuitement et lancez votre boutique en ligne.' })

const { register } = useAuth()
const route = useRoute()
const toast = useToast()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const submitting = ref(false)

const fields = [
  { key: 'name', label: 'Nom complet', type: 'text', autocomplete: 'name' },
  { key: 'email', label: 'Adresse e-mail', type: 'email', autocomplete: 'email' },
  { key: 'password', label: 'Mot de passe', type: 'password', autocomplete: 'new-password' },
  { key: 'password_confirmation', label: 'Confirmez le mot de passe', type: 'password', autocomplete: 'new-password' },
] as const

async function submit(): Promise<void> {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    await register({ ...form })
    toast.success('Bienvenue sur ShopAI ! Créons votre boutique.')
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/creer-ma-boutique'
    await navigateTo(redirect)
  }
  catch (error) {
    errors.value = validationErrors(error)
    generalError.value = Object.keys(errors.value).length ? '' : errorMessage(error)
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="auth-shell">
    <section class="container py-5" style="max-width: 500px;">
      <p class="text-center mb-4"><span class="section-eyebrow mb-0">Espace marchand</span></p>
      <div class="card auth-card p-4 p-md-5">
        <h1 class="h2 mb-1">Créer un compte</h1>
        <p class="text-muted mb-4">Gratuit, sans engagement. Votre boutique est à 5 minutes.</p>
        <div v-if="generalError" class="alert alert-danger" role="alert">{{ generalError }}</div>
        <form novalidate @submit.prevent="submit">
          <div v-for="field in fields" :key="field.key" class="mb-3">
            <label :for="`register-${field.key}`" class="form-label">{{ field.label }}</label>
            <input
              :id="`register-${field.key}`"
              v-model="form[field.key]"
              :type="field.type"
              class="form-control form-control-lg"
              :class="{ 'is-invalid': errors[field.key] }"
              :autocomplete="field.autocomplete"
              required
              :aria-describedby="`register-${field.key}-error`"
            >
            <FieldError :id="`register-${field.key}-error`" :message="errors[field.key]" />
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100 mt-2" :disabled="submitting">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
            Créer mon compte
          </button>
        </form>
        <p class="text-center text-muted mt-4 mb-0">
          Déjà inscrit ? <NuxtLink :to="{ path: '/login', query: route.query }" class="fw-semibold">Se connecter</NuxtLink>
        </p>
      </div>
    </section>
  </div>
</template>

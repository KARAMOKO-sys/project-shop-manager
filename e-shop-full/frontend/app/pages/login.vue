<script setup lang="ts">
definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Connexion', description: 'Connectez-vous à votre espace marchand ShopAI.' })

const { login } = useAuth()
const route = useRoute()
const toast = useToast()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const submitting = ref(false)

async function submit(): Promise<void> {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const user = await login({ ...form })
    toast.success(`Bon retour, ${user.name} !`)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/dashboard'
    await navigateTo(redirect)
  }
  catch (error) {
    errors.value = validationErrors(error)
    generalError.value = Object.keys(errors.value).length ? '' : errorMessage(error, 'Identifiants incorrects.')
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="auth-shell">
    <section class="container py-5" style="max-width: 460px;">
      <p class="text-center mb-4"><span class="section-eyebrow mb-0">Espace marchand</span></p>
      <div class="card auth-card p-4 p-md-5">
        <h1 class="h2 mb-1">Connexion</h1>
        <p class="text-muted mb-4">Ravi de vous revoir ! Accédez à votre espace marchand.</p>
        <div v-if="generalError" class="alert alert-danger" role="alert">{{ generalError }}</div>
        <form novalidate @submit.prevent="submit">
          <div class="mb-3">
            <label for="login-email" class="form-label">Adresse e-mail</label>
            <input
              id="login-email"
              v-model="form.email"
              type="email"
              class="form-control form-control-lg"
              :class="{ 'is-invalid': errors.email }"
              autocomplete="email"
              required
              aria-describedby="login-email-error"
            >
            <FieldError id="login-email-error" :message="errors.email" />
          </div>
          <div class="mb-4">
            <label for="login-password" class="form-label">Mot de passe</label>
            <input
              id="login-password"
              v-model="form.password"
              type="password"
              class="form-control form-control-lg"
              :class="{ 'is-invalid': errors.password }"
              autocomplete="current-password"
              required
              aria-describedby="login-password-error"
            >
            <FieldError id="login-password-error" :message="errors.password" />
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="submitting">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
            Se connecter
          </button>
        </form>
        <p class="text-center text-muted mt-4 mb-0">
          Pas encore de compte ? <NuxtLink :to="{ path: '/inscription', query: route.query }" class="fw-semibold">Créer un compte</NuxtLink>
        </p>
      </div>
    </section>
  </div>
</template>

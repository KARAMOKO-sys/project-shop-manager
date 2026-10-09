export default defineNuxtRouteMiddleware(() => {
  if (useAuthToken().value) {
    return navigateTo('/dashboard')
  }
})

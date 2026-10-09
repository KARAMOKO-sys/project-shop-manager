export default defineNuxtRouteMiddleware((to) => {
  if (!useAuthToken().value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
})

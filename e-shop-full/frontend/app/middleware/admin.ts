export default defineNuxtRouteMiddleware(async (to) => {
  const { isAuthenticated, user, fetchMe } = useAuth()

  if (!isAuthenticated.value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  const currentUser = user.value ?? await fetchMe()

  if (!currentUser?.is_admin) {
    return navigateTo('/dashboard')
  }
})

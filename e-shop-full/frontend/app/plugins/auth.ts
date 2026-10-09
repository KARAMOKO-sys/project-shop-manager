export default defineNuxtPlugin({
  name: 'auth',
  async setup() {
    const user = useAuthUser()

    if (!user.value) {
      await useAuth().fetchMe()
    }
  },
})

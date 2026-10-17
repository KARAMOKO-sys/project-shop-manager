export default defineNuxtPlugin({
  name: 'platform',
  async setup() {
    await usePlatform().load()
  },
})

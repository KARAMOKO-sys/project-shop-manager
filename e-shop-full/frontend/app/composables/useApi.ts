import type { User } from '~/types'

const TOKEN_COOKIE = 'shopai_token'

/** Bearer token persisted in the `shopai_token` cookie and mirrored in shared state for reactivity. */
export function useAuthToken() {
  const cookie = useCookie<string | null>(TOKEN_COOKIE, {
    default: () => null,
    maxAge: 60 * 60 * 24 * 30,
    sameSite: 'lax',
    path: '/',
  })
  const token = useState<string | null>('auth-token', () => cookie.value ?? null)

  return computed<string | null>({
    get: () => token.value,
    set: (value) => {
      token.value = value
      cookie.value = value
    },
  })
}

export function useAuthUser() {
  return useState<User | null>('auth-user', () => null)
}

export function useApi() {
  const config = useRuntimeConfig()
  const nuxtApp = useNuxtApp()
  const token = useAuthToken()
  const user = useAuthUser()

  return $fetch.create({
    baseURL: config.public.apiBase,
    timeout: 20000,
    onRequest({ options }) {
      const headers = new Headers(options.headers)
      headers.set('Accept', 'application/json')

      if (options.body instanceof FormData) {
        // Let the browser set the multipart boundary itself.
        headers.delete('Content-Type')
      }

      if (token.value) {
        headers.set('Authorization', `Bearer ${token.value}`)
      }

      options.headers = headers
    },
    async onResponseError({ request, response }) {
      if (response.status !== 401 || !token.value) {
        return
      }

      token.value = null
      user.value = null

      if (!String(request).includes('/auth/')) {
        await nuxtApp.runWithContext(() => navigateTo('/login'))
      }
    },
  })
}

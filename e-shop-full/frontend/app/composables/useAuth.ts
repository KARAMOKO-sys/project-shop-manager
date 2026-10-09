import type { AuthPayload, Resource, User } from '~/types'

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterPayload extends LoginCredentials {
  name: string
  password_confirmation: string
}

export function useAuth() {
  const api = useApi()
  const token = useAuthToken()
  const user = useAuthUser()

  const isAuthenticated = computed(() => Boolean(token.value))
  const isAdmin = computed(() => Boolean(user.value?.is_admin))

  async function authenticate(path: string, body: LoginCredentials | RegisterPayload): Promise<User> {
    const response = await api<Resource<AuthPayload>>(path, { method: 'POST', body })
    token.value = response.data.token
    user.value = response.data.user

    return response.data.user
  }

  const login = (credentials: LoginCredentials) => authenticate('/auth/login', credentials)
  const register = (payload: RegisterPayload) => authenticate('/auth/register', payload)

  async function fetchMe(): Promise<User | null> {
    if (!token.value) {
      return null
    }

    try {
      const response = await api<Resource<User>>('/auth/me', { timeout: 6000 })
      user.value = response.data
    }
    catch (error) {
      if (statusOf(error) === 401) {
        token.value = null
        user.value = null
      }
    }

    return user.value
  }

  async function logout(): Promise<void> {
    try {
      await api('/auth/logout', { method: 'POST' })
    }
    catch {
      // The token may already be revoked: the local session is cleared regardless.
    }
    finally {
      token.value = null
      user.value = null
    }

    await navigateTo('/')
  }

  return { user, token, isAuthenticated, isAdmin, login, register, fetchMe, logout }
}

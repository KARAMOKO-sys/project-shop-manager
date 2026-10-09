interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}

function payloadOf(error: unknown): ApiErrorPayload | undefined {
  if (error && typeof error === 'object' && 'data' in error) {
    const data = (error as { data: unknown }).data

    if (data && typeof data === 'object') {
      return data as ApiErrorPayload
    }
  }

  return undefined
}

export function statusOf(error: unknown): number | undefined {
  if (error && typeof error === 'object') {
    const candidate = error as { statusCode?: number, status?: number }

    return candidate.statusCode ?? candidate.status
  }

  return undefined
}

/** Flattens Laravel 422 errors into a `{ field: firstMessage }` map. */
export function validationErrors(error: unknown): Record<string, string> {
  const errors = payloadOf(error)?.errors ?? {}

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] ?? '' : String(messages)]),
  )
}

export function errorMessage(error: unknown, fallback = 'Une erreur est survenue. Veuillez réessayer.'): string {
  const payload = payloadOf(error)

  if (payload?.message) {
    return payload.message
  }

  if (statusOf(error) === undefined) {
    return 'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.'
  }

  return fallback
}

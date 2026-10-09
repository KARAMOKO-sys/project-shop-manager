import type { Resource, Template } from '~/types'

/** Template catalog from GET /templates, shared (same key) by the gallery, the wizard and the dashboard. */
export function useTemplates() {
  const api = useApi()

  return useAsyncData('templates', () => api<Resource<Template[]>>('/templates').then(response => response.data ?? []))
}

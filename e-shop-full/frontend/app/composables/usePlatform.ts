import type { PlatformConfig, Resource } from '~/types'

/** Used until GET /platform answers, and if it fails: the platform keeps working with everything enabled. */
const DEFAULT_PLATFORM: PlatformConfig = {
  platform: {
    name: 'ShopAI',
    tagline: 'La plateforme e-commerce propulsée par l’IA pour les commerçants d’Afrique francophone.',
    support_email: null,
    support_whatsapp: null,
    announcement: null,
  },
  features: {
    registration: true,
    shop_creation: true,
    ai_assistant: true,
    platform_chat: true,
    shop_chat: true,
    recommendations: true,
  },
  payments: {
    cash_on_delivery: true,
    online: true,
  },
}

/** Public platform settings (branding, announcement, enabled features), shared app-wide. */
export function usePlatform() {
  const config = useState<PlatformConfig>('platform-config', () => structuredClone(DEFAULT_PLATFORM))

  async function load(): Promise<void> {
    try {
      const response = await useApi()<Resource<PlatformConfig>>('/platform', { timeout: 6000 })
      config.value = response.data
    }
    catch {
      // Keep the defaults: an unreachable settings endpoint must not take the site down.
    }
  }

  const features = computed(() => config.value.features)

  return { config, features, load }
}

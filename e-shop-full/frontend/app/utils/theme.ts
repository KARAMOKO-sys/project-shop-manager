import type { ShopFormat, Theme } from '~/types'

export const DEFAULT_THEME: Theme = {
  primary: '#0F3D2E',
  secondary: '#C8963E',
  background: '#FAF7F0',
  surface: '#ffffff',
  text: '#1C1B17',
  font: 'Inter',
  radius: '12px',
  hero_style: 'gradient',
  layout: 'grid',
  format: 'classic',
}

export interface ShopFormatOption {
  value: ShopFormat
  label: string
  description: string
  icon: string
}

/** Page structures a storefront can use (labels shown to merchants). */
export const SHOP_FORMATS: ShopFormatOption[] = [
  { value: 'classic', label: 'Classique', description: 'Bannière, catégories et grille de produits.', icon: 'bi-grid' },
  { value: 'editorial', label: 'Éditorial', description: 'Mise en page magazine qui raconte vos produits.', icon: 'bi-journal-richtext' },
  { value: 'catalog', label: 'Catalogue', description: 'Catalogue dense avec filtres, idéal pour beaucoup de références.', icon: 'bi-funnel' },
  { value: 'menu', label: 'Menu', description: 'Carte de restaurant par catégories, commande rapide.', icon: 'bi-list-ul' },
  { value: 'showcase', label: 'Vitrine', description: 'Grands visuels et beaucoup d’espace pour le haut de gamme.', icon: 'bi-gem' },
  { value: 'single_product', label: 'Produit unique', description: 'Page de vente centrée sur votre produit phare.', icon: 'bi-bullseye' },
]

/** Returns a known format, falling back to `classic` for missing or unknown values. */
export function resolveFormat(format: string | null | undefined): ShopFormat {
  return SHOP_FORMATS.some(option => option.value === format) ? format as ShopFormat : 'classic'
}

export function formatLabel(format: string | null | undefined): string {
  const value = resolveFormat(format)

  return SHOP_FORMATS.find(option => option.value === value)?.label ?? 'Classique'
}

export const THEME_FONTS = ['Plus Jakarta Sans', 'Inter', 'Poppins', 'Montserrat', 'Nunito', 'DM Sans', 'Playfair Display', 'Lora', 'Space Grotesk']

const SYSTEM_FONTS = ['system-ui', 'sans-serif', 'serif', 'Arial', 'Helvetica', 'Georgia']

function normalizeHex(hex: string): string | null {
  const raw = hex.trim().replace('#', '')
  const full = raw.length === 3 ? raw.split('').map(char => char + char).join('') : raw.slice(0, 6)

  return /^[0-9a-f]{6}$/i.test(full) ? full : null
}

export function hexToRgb(hex: string): string {
  const full = normalizeHex(hex) ?? '0f3d2e'
  const value = Number.parseInt(full, 16)

  return `${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}`
}

/** Picks white or near-black text for the best contrast on the given background color. */
export function contrastText(hex: string): string {
  const [r, g, b] = hexToRgb(hex).split(',').map(part => Number(part) / 255)
  const linear = (channel: number) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
  const luminance = 0.2126 * linear(r ?? 0) + 0.7152 * linear(g ?? 0) + 0.0722 * linear(b ?? 0)

  return luminance > 0.45 ? '#1C1B17' : '#ffffff'
}

export function radiusToCss(radius: string | number | null | undefined): string {
  if (radius === null || radius === undefined || radius === '') {
    return String(DEFAULT_THEME.radius)
  }

  if (typeof radius === 'number' || /^\d+(\.\d+)?$/.test(radius)) {
    return `${radius}px`
  }

  return radius
}

export function radiusToPx(radius: string | number | null | undefined): number {
  const css = radiusToCss(radius)
  const value = Number.parseFloat(css)

  if (Number.isNaN(value)) {
    return 14
  }

  return css.endsWith('rem') ? value * 16 : value
}

export function resolveTheme(theme?: Partial<Theme> | null): Theme {
  const merged = { ...DEFAULT_THEME, ...(theme ?? {}) }

  return { ...merged, format: resolveFormat(merged.format) }
}

export function fontStack(font: string): string {
  return `'${font}', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`
}

export function googleFontUrl(font: string | undefined): string | null {
  if (!font || SYSTEM_FONTS.includes(font)) {
    return null
  }

  return `https://fonts.googleapis.com/css2?family=${encodeURIComponent(font).replace(/%20/g, '+')}:wght@400;500;600;700&display=swap`
}

/** One stylesheet URL loading several theme fonts (used by template galleries). */
export function googleFontsUrl(fonts: (string | undefined)[]): string | null {
  const families = [...new Set(fonts.filter((font): font is string => Boolean(font) && !SYSTEM_FONTS.includes(font!)))]

  if (!families.length) {
    return null
  }

  const query = families.map(font => `family=${encodeURIComponent(font).replace(/%20/g, '+')}:wght@400;600;700`).join('&')

  return `https://fonts.googleapis.com/css2?${query}&display=swap`
}

/** CSS custom properties that re-skin Bootstrap and the app components with a shop theme. */
export function themeVars(theme?: Partial<Theme> | null): Record<string, string> {
  const resolved = resolveTheme(theme)
  const primaryRgb = hexToRgb(resolved.primary)
  const radius = radiusToCss(resolved.radius)

  return {
    '--app-primary': resolved.primary,
    '--app-primary-rgb': primaryRgb,
    '--app-secondary': resolved.secondary,
    '--app-on-primary': contrastText(resolved.primary),
    '--shop-bg': resolved.background,
    '--shop-surface': resolved.surface,
    '--shop-text': resolved.text,
    '--shop-font': fontStack(resolved.font),
    '--shop-radius': radius,
    '--app-accent': resolved.secondary,
    '--app-accent-ink': `color-mix(in srgb, ${resolved.secondary} 55%, ${resolved.text})`,
    '--app-accent-soft': `color-mix(in srgb, ${resolved.secondary} 16%, ${resolved.surface})`,
    '--app-primary-soft': `color-mix(in srgb, ${resolved.primary} 10%, ${resolved.surface})`,
    '--app-ink': resolved.text,
    '--app-muted': `color-mix(in srgb, ${resolved.text} 68%, ${resolved.surface})`,
    '--app-border': `color-mix(in srgb, ${resolved.text} 12%, transparent)`,
    '--app-focus': resolved.primary,
    '--app-heading-font': fontStack(resolved.font),
    '--app-btn-radius': radius,
    '--bs-primary': resolved.primary,
    '--bs-primary-rgb': primaryRgb,
    '--bs-link-color': resolved.primary,
    '--bs-link-color-rgb': primaryRgb,
    '--bs-link-hover-color': resolved.primary,
    '--bs-link-hover-color-rgb': primaryRgb,
    '--bs-body-bg': resolved.surface,
    '--bs-body-color': resolved.text,
    '--bs-border-radius': radius,
    '--bs-border-radius-lg': `calc(${radius} * 1.35)`,
    '--bs-border-radius-sm': `calc(${radius} * 0.6)`,
    '--bs-border-color': `color-mix(in srgb, ${resolved.text} 14%, transparent)`,
  }
}

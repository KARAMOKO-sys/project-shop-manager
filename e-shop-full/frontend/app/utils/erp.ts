import type { ExpenseCategory, InvoicePaymentMethod, InvoiceStatus, LeadSource, LeadStage, PurchaseOrderStatus } from '~/types'

/** Navigation of the ERP applications, in display order. `module` null = always shown. */
export const ERP_NAV: { module: string | null, path: string, label: string, icon: string }[] = [
  { module: null, path: '', label: 'Tableau de bord', icon: 'bi-speedometer2' },
  { module: 'inventory', path: '/inventaire', label: 'Inventaire', icon: 'bi-boxes' },
  { module: 'purchase', path: '/achats', label: 'Achats', icon: 'bi-truck' },
  { module: 'invoicing', path: '/factures', label: 'Facturation', icon: 'bi-receipt-cutoff' },
  { module: 'expenses', path: '/depenses', label: 'Dépenses', icon: 'bi-wallet2' },
  { module: 'crm', path: '/crm', label: 'CRM', icon: 'bi-kanban' },
  { module: null, path: '/applications', label: 'Applications', icon: 'bi-grid-3x3-gap' },
]

export const MODULE_NAMES: Record<string, string> = {
  sales: 'Ventes & boutique en ligne',
  inventory: 'Inventaire',
  purchase: 'Achats',
  invoicing: 'Facturation',
  expenses: 'Dépenses',
  crm: 'CRM',
}

export const PURCHASE_STATUS_VARIANTS: Record<PurchaseOrderStatus, string> = {
  draft: 'secondary',
  confirmed: 'primary',
  partially_received: 'warning',
  received: 'success',
  cancelled: 'danger',
}

export const PURCHASE_STATUSES: { value: PurchaseOrderStatus, label: string }[] = [
  { value: 'draft', label: 'Brouillon' },
  { value: 'confirmed', label: 'Confirmé' },
  { value: 'partially_received', label: 'Partiellement reçu' },
  { value: 'received', label: 'Reçu' },
  { value: 'cancelled', label: 'Annulé' },
]

export const INVOICE_STATUS_VARIANTS: Record<InvoiceStatus, string> = {
  draft: 'secondary',
  posted: 'primary',
  partially_paid: 'warning',
  paid: 'success',
  cancelled: 'danger',
}

export const INVOICE_STATUSES: { value: InvoiceStatus, label: string }[] = [
  { value: 'draft', label: 'Brouillon' },
  { value: 'posted', label: 'Validée' },
  { value: 'partially_paid', label: 'Partiellement payée' },
  { value: 'paid', label: 'Payée' },
  { value: 'cancelled', label: 'Annulée' },
]

export const INVOICE_PAYMENT_METHODS: { value: InvoicePaymentMethod, label: string }[] = [
  { value: 'cash', label: 'Espèces' },
  { value: 'mobile_money', label: 'Mobile Money' },
  { value: 'card', label: 'Carte bancaire' },
  { value: 'bank_transfer', label: 'Virement bancaire' },
  { value: 'other', label: 'Autre' },
]

export const EXPENSE_CATEGORIES: { value: ExpenseCategory, label: string, icon: string }[] = [
  { value: 'rent', label: 'Loyer', icon: 'bi-house' },
  { value: 'salaries', label: 'Salaires', icon: 'bi-people' },
  { value: 'transport', label: 'Transport & livraison', icon: 'bi-truck' },
  { value: 'marketing', label: 'Marketing & publicité', icon: 'bi-megaphone' },
  { value: 'utilities', label: 'Électricité, eau, internet', icon: 'bi-lightning-charge' },
  { value: 'supplies', label: 'Fournitures & emballages', icon: 'bi-box2' },
  { value: 'taxes', label: 'Impôts & taxes', icon: 'bi-bank' },
  { value: 'fees', label: 'Frais bancaires & Mobile Money', icon: 'bi-phone' },
  { value: 'other', label: 'Autre', icon: 'bi-three-dots' },
]

export function expenseCategoryLabel(category: string): string {
  return EXPENSE_CATEGORIES.find(item => item.value === category)?.label ?? category
}

export const LEAD_STAGES: { value: LeadStage, label: string, variant: string }[] = [
  { value: 'new', label: 'Nouveau', variant: 'secondary' },
  { value: 'qualified', label: 'Qualifié', variant: 'info' },
  { value: 'proposal', label: 'Proposition', variant: 'primary' },
  { value: 'won', label: 'Gagné', variant: 'success' },
  { value: 'lost', label: 'Perdu', variant: 'danger' },
]

export const LEAD_SOURCES: { value: LeadSource, label: string }[] = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'storefront', label: 'Boutique en ligne' },
  { value: 'referral', label: 'Recommandation' },
  { value: 'walk_in', label: 'Visite en boutique' },
  { value: 'event', label: 'Salon / événement' },
  { value: 'other', label: 'Autre' },
]

/** Today as `YYYY-MM-DD` in local time, for date inputs. */
export function todayIso(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000

  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}

/** Builds a query string, dropping empty values. */
export function queryString(params: Record<string, string | number | null | undefined>): string {
  const query = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '') {
      query.set(key, String(value))
    }
  }

  const result = query.toString()

  return result ? `?${result}` : ''
}

/** Formats a `YYYY-MM-DD` date like "16 nov. 2026" (no time, no timezone shift). */
export function formatDay(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const [year, month, day] = value.slice(0, 10).split('-').map(Number)

  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(year!, (month ?? 1) - 1, day ?? 1))
}

/** A ref mirroring `source` after `delay` ms without change (search inputs). */
export function debouncedRef<T>(source: Ref<T>, delay = 350): Ref<T> {
  const debounced = ref(source.value) as Ref<T>
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(source, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => (debounced.value = value), delay)
  })

  return debounced
}

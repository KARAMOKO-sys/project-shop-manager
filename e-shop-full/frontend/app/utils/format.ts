import type { OrderStatus, PaymentMethod } from '~/types'

const CFA_CURRENCIES = ['XOF', 'XAF']

export function formatNumber(value: number | string | null | undefined, fractionDigits = 0): string {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: fractionDigits }).format(Number(value ?? 0))
}

/** Formats an amount like "4 500 FCFA" for CFA francs, or with the regular currency symbol otherwise. */
export function formatPrice(amount: number | string | null | undefined, currency = 'XOF'): string {
  const code = (currency || 'XOF').toUpperCase()
  const value = Number(amount ?? 0)

  if (CFA_CURRENCIES.includes(code)) {
    return `${formatNumber(value)} FCFA`
  }

  try {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: code }).format(value)
  }
  catch {
    return `${formatNumber(value, 2)} ${code}`
  }
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

export const CURRENCIES = [
  { value: 'XOF', label: 'Franc CFA (BCEAO) — XOF' },
  { value: 'XAF', label: 'Franc CFA (BEAC) — XAF' },
  { value: 'GNF', label: 'Franc guinéen — GNF' },
  { value: 'MAD', label: 'Dirham marocain — MAD' },
  { value: 'EUR', label: 'Euro — EUR' },
  { value: 'USD', label: 'Dollar US — USD' },
]

export const ORDER_STATUSES: { value: OrderStatus, label: string, variant: string }[] = [
  { value: 'received', label: 'Reçue', variant: 'secondary' },
  { value: 'paid', label: 'Payée', variant: 'info' },
  { value: 'processing', label: 'En préparation', variant: 'primary' },
  { value: 'shipped', label: 'Expédiée', variant: 'warning' },
  { value: 'delivered', label: 'Livrée', variant: 'success' },
  { value: 'cancelled', label: 'Annulée', variant: 'danger' },
]

/** Next statuses a merchant may move an order to (mirrors the backend state machine). */
export const ORDER_TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  received: ['paid', 'processing', 'cancelled'],
  paid: ['processing', 'cancelled'],
  processing: ['shipped', 'cancelled'],
  shipped: ['delivered'],
  delivered: [],
  cancelled: [],
}

/** Current status followed by the statuses it can legally move to. */
export function allowedOrderStatuses(status: OrderStatus) {
  const next = ORDER_TRANSITIONS[status] ?? []

  return ORDER_STATUSES.filter(item => item.value === status || next.includes(item.value))
}

/** Linear progression shown in the customer tracking timeline (cancelled excluded). */
export const ORDER_TIMELINE: OrderStatus[] = ['received', 'paid', 'processing', 'shipped', 'delivered']

export function formatShortDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)

  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(date)
}

/** "10 %" for percentage coupons, "500 FCFA" for fixed ones. */
export function formatCouponValue(coupon: { type: string, value: number | string }, currency = 'XOF'): string {
  return coupon.type === 'percentage' ? `${formatNumber(coupon.value, 2)} %` : formatPrice(coupon.value, currency)
}

export function orderStatusMeta(status: string) {
  return ORDER_STATUSES.find(item => item.value === status) ?? { value: status, label: status, variant: 'secondary' }
}

export const PAYMENT_METHODS: { value: PaymentMethod, label: string, description: string, icon: string }[] = [
  { value: 'cash_on_delivery', label: 'Paiement à la livraison', description: 'Réglez en espèces à la réception de votre commande.', icon: 'bi-cash-coin' },
  { value: 'flutterwave', label: 'Carte ou Mobile Money', description: 'Paiement sécurisé par les moyens proposés pour votre pays.', icon: 'bi-shield-lock' },
]

export function paymentMethodLabel(method: string): string {
  if (method === 'mobile_money') {
    return 'Mobile Money'
  }

  if (method === 'card') {
    return 'Carte bancaire'
  }

  return PAYMENT_METHODS.find(item => item.value === method)?.label ?? method
}

export function paymentStatusLabel(status: string): string {
  return ({
    pending: 'En attente',
    paid: 'Payé',
    failed: 'Échoué',
    refunded: 'Remboursé',
  } as Record<string, string>)[status] ?? status
}

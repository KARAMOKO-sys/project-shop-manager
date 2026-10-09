export interface User {
  id: number
  name: string
  email: string
  is_admin: boolean
}

export interface AuthPayload {
  user: User
  token: string
}

export type HeroStyle = 'gradient' | 'image' | 'minimal'
export type ProductLayout = 'grid' | 'list' | 'masonry'
/** Page structure of a storefront. A missing or unknown value is treated as `classic`. */
export type ShopFormat = 'classic' | 'editorial' | 'catalog' | 'menu' | 'showcase' | 'single_product'

export interface Theme {
  primary: string
  secondary: string
  background: string
  surface: string
  text: string
  font: string
  radius: string | number
  hero_style: HeroStyle
  layout: ProductLayout
  format: ShopFormat
}

export interface Template {
  key: string
  name: string
  sector: string
  description: string
  ideal_for: string[]
  theme: Theme
  default_categories: string[]
}

export interface Category {
  id: number
  name: string
  slug: string
}

export interface Shop {
  id: number
  name: string
  slug: string
  description: string | null
  tagline: string | null
  sector: string | null
  template: string
  theme: Theme
  currency: string
  language: string
  logo_url: string | null
  status: string
  products_count?: number
  categories?: Category[]
  url: string
}

export type ModerationStatus = 'pending' | 'approved' | 'pending_review' | 'rejected'

export interface Moderation {
  status: ModerationStatus
  risk_score: number
  reasons: string[]
  checked_at: string | null
  engine: 'local' | 'claude'
}

export type ProductStatus = 'draft' | 'published'

export interface Product {
  id: number
  shop_id: number
  shop?: { id: number, name: string, slug: string }
  category_id: number | null
  category?: Category | null
  name: string
  slug: string
  sku: string | null
  description: string | null
  price: number
  compare_at_price: number | null
  currency: string
  stock_quantity: number
  status: ProductStatus
  is_featured: boolean
  image_url: string | null
  tags: string[]
  moderation: Moderation
  is_public: boolean
  created_at: string
}

export type OrderStatus = 'received' | 'paid' | 'processing' | 'shipped' | 'delivered' | 'cancelled'
export type PaymentMethod = 'cash_on_delivery' | 'mobile_money' | 'card'

export interface Customer {
  first_name: string
  last_name: string
  email: string
  phone: string
}

export interface OrderItem {
  product_id: number
  product_name: string
  quantity: number
  unit_price: number
  total_price: number
}

export interface Order {
  id: number
  order_number: string
  status: OrderStatus
  subtotal: number
  shipping_amount: number
  discount_amount: number
  total: number
  currency: string
  payment_method: PaymentMethod
  payment_status: string
  customer: Customer
  items: OrderItem[]
  created_at: string
}

export interface Analytics {
  revenue: number
  orders: number
  customers: number
  products: number
  average_order_value: number
  pending_moderation: number
  orders_by_status: Partial<Record<OrderStatus, number>>
  low_stock_products?: LowStockProduct[]
  revenue_by_day?: RevenueDay[]
}

export interface LowStockProduct {
  id: number
  name: string
  stock_quantity: number
}

export interface RevenueDay {
  /** Day formatted as `YYYY-MM-DD`. */
  date: string
  revenue: number
  orders: number
}

export type CouponType = 'percentage' | 'fixed'
export type CouponStatus = 'active' | 'inactive'

export interface Coupon {
  id: number
  code: string
  type: CouponType
  value: number
  min_order_amount: number
  starts_at: string | null
  ends_at: string | null
  /** 0 means unlimited. */
  usage_limit: number
  times_used: number
  status: CouponStatus
  is_redeemable: boolean
}

export interface CouponPayload {
  code: string
  type: CouponType
  value: number
  min_order_amount?: number | null
  starts_at?: string | null
  ends_at?: string | null
  usage_limit?: number | null
  status?: CouponStatus
}

export interface CouponCheck {
  code: string
  type: CouponType
  value: number
  discount_amount: number
}

export interface UploadedImage {
  url: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Resource<T> {
  data: T
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface HomeData {
  featured_products: Product[]
  shops: Shop[]
  templates: Template[]
  stats: { shops: number, products: number }
}

export interface ChatTurn {
  role: 'user' | 'assistant'
  content: string
}

export interface ChatReply {
  reply: string
  suggestions?: string[]
  products?: Product[]
}

export interface ShopSetupSuggestion {
  name: string
  tagline: string
  sector: string
  template: string
  description: string
  categories: string[]
}

export type DescriptionTone = 'professionnel' | 'decontracte' | 'luxe'

export interface CartItem {
  product_id: number
  slug: string
  name: string
  price: number
  currency: string
  image_url: string | null
  quantity: number
}

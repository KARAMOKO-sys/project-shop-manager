export type UserRole = 'merchant' | 'moderator' | 'super_admin'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  /** Super administrator: platform settings and moderator accounts. */
  is_admin: boolean
  /** Moderators and super administrators review flagged products. */
  can_moderate: boolean
}

export interface Moderator {
  id: number
  name: string
  email: string
  role: UserRole
  is_suspended: boolean
  suspended_at: string | null
  decisions_count: number
  created_by: string | null
  created_at: string
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
  /** VAT rate (%) applied by default on invoice lines. */
  tax_rate: number
  logo_url: string | null
  status: string
  cash_on_delivery_enabled: boolean
  flutterwave_enabled: boolean
  flutterwave_configured: boolean
  payment_methods: PaymentMethod[]
  delivery: ShopDelivery
  /** Installed ERP applications (`sales` is always present). */
  modules: ErpModuleKey[]
  products_count?: number
  categories?: Category[]
  url: string
}

export type DeliveryMethod = 'delivery' | 'pickup'

/** How a shop delivers: flat fee, free from a threshold, optional in-store pickup. */
export interface ShopDelivery {
  enabled: boolean
  fee: number
  free_threshold: number | null
  zone: string | null
  delay: string | null
  pickup_enabled: boolean
  pickup_address: string | null
  methods: DeliveryMethod[]
}

export interface OrderDelivery {
  method: DeliveryMethod
  address: string | null
  city: string | null
}

export type ModerationStatus = 'pending' | 'approved' | 'pending_review' | 'rejected'

export interface Moderation {
  status: ModerationStatus
  risk_score: number
  reasons: string[]
  checked_at: string | null
  note?: string | null
  engine: 'local' | 'claude' | 'human' | null
  /** Name of the moderator who decided, when the decision was taken by a human. */
  reviewed_by?: string | null
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
export type PaymentMethod = 'cash_on_delivery' | 'mobile_money' | 'card' | 'flutterwave'

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
  delivery: OrderDelivery
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

export interface PlatformFeatures {
  registration: boolean
  shop_creation: boolean
  ai_assistant: boolean
  platform_chat: boolean
  shop_chat: boolean
  recommendations: boolean
}

/** Public platform settings from GET /platform. */
export interface PlatformConfig {
  platform: {
    name: string
    tagline: string | null
    support_email: string | null
    support_whatsapp: string | null
    announcement: string | null
  }
  features: PlatformFeatures
  payments: {
    cash_on_delivery: boolean
    online: boolean
  }
}

export type PlatformSettingType = 'string' | 'text' | 'email' | 'boolean' | 'integer' | 'select'

export interface PlatformSettingDefinition {
  group: string
  type: PlatformSettingType
  label: string
  help?: string
  public: boolean
  options?: Record<string, string>
  min?: number
  max?: number
}

/** Administration payload from GET /admin/settings; values are keyed by dotted name. */
export interface PlatformSettingsPayload {
  groups: Record<string, string>
  definitions: Record<string, PlatformSettingDefinition>
  values: Record<string, string | number | boolean | null>
}

/* ------------------------------------------------------------------ */
/* Modular ERP                                                         */
/* ------------------------------------------------------------------ */

export type ErpModuleKey = 'sales' | 'inventory' | 'purchase' | 'invoicing' | 'expenses' | 'crm' | (string & {})

export interface ErpModule {
  key: ErpModuleKey
  name: string
  summary: string
  category: string
  icon: string
  depends: ErpModuleKey[]
  core: boolean
  installed: boolean
  installed_at: string | null
  /** Installed modules that depend on this one (it cannot be removed while they are installed). */
  required_by: ErpModuleKey[]
}

/** Laravel resource pagination (`links` omitted). */
export interface ErpPaginated<T> {
  data: T[]
  meta: { current_page: number, last_page: number, total: number, per_page?: number }
}

export interface Warehouse {
  id: number
  name: string
  code: string
  address: string | null
  is_default: boolean
  products_count?: number
  stock_quantity?: number
}

export interface StockRow {
  id: number
  name: string
  sku: string | null
  image_url: string | null
  stock_quantity: number
  reorder_point: number | null
  cost_price: number | null
  stock_value: number
  stock_status: string
  warehouses: { warehouse_id: number, code: string, name: string, quantity: number }[]
}

export type StockMovementType = 'opening' | 'receipt' | 'sale' | 'sale_return' | 'adjustment' | 'transfer_in' | 'transfer_out'

export interface StockMovement {
  id: number
  type: StockMovementType
  type_label: string
  quantity: number
  warehouse_quantity_after: number
  reference: string | null
  note: string | null
  product: { id: number, name: string, sku: string | null }
  warehouse: { id: number, name: string, code: string }
  user: string | null
  created_at: string
}

export interface ReorderSuggestion {
  product: { id: number, name: string, sku: string | null, image_url: string | null }
  stock: number
  incoming: number
  reorder_point: number
  sales_velocity: number
  days_of_cover: number | null
  stockout_date: string | null
  suggested_quantity: number
  estimated_cost: number
  urgency: 'critical' | 'warning'
  supplier: { id: number, name: string, lead_time_days: number } | null
}

export interface Supplier {
  id: number
  name: string
  contact_name: string | null
  email: string | null
  phone: string | null
  address: string | null
  payment_terms_days: number
  lead_time_days: number
  notes: string | null
  purchase_orders_count?: number
}

export type PurchaseOrderStatus = 'draft' | 'confirmed' | 'partially_received' | 'received' | 'cancelled'

export interface PurchaseOrderLine {
  id: number
  product: { id: number, name: string, sku: string | null }
  quantity: number
  quantity_received: number
  remaining_quantity: number
  unit_cost: number
  total: number
}

export interface PurchaseOrder {
  id: number
  number: string
  status: PurchaseOrderStatus
  status_label: string
  supplier: { id: number, name: string }
  warehouse: { id: number, name: string, code: string }
  expected_at: string | null
  confirmed_at: string | null
  received_at: string | null
  total: number
  currency: string
  notes: string | null
  is_late: boolean
  lines?: PurchaseOrderLine[]
  lines_count?: number
  created_at: string
}

export type InvoiceStatus = 'draft' | 'posted' | 'partially_paid' | 'paid' | 'cancelled'
export type InvoicePaymentMethod = 'cash' | 'mobile_money' | 'card' | 'bank_transfer' | 'other'

export interface InvoiceLine {
  id: number
  product_id: number | null
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
  total: number
}

export interface InvoicePayment {
  id: number
  amount: number
  method: InvoicePaymentMethod
  paid_at: string
  reference: string | null
}

export interface Invoice {
  id: number
  number: string | null
  status: InvoiceStatus
  status_label: string
  customer_id: number | null
  order: { id: number, order_number: string } | null
  customer_name: string
  customer_email: string | null
  issue_date: string
  due_date: string
  subtotal: number
  tax_amount: number
  total: number
  amount_paid: number
  amount_due: number
  currency: string
  notes: string | null
  is_overdue: boolean
  posted_at: string | null
  created_at: string
  lines?: InvoiceLine[]
  payments?: InvoicePayment[]
}

export interface InvoiceableOrder {
  id: number
  order_number: string
  customer_name: string
  total: number
  currency: string
  status: OrderStatus
  created_at: string
}

export type ExpenseCategory = 'rent' | 'salaries' | 'transport' | 'marketing' | 'utilities' | 'supplies' | 'taxes' | 'fees' | 'other'

export interface Expense {
  id: number
  category: ExpenseCategory
  category_label: string
  label: string
  amount: number
  currency: string
  spent_at: string
  payment_method: string | null
  reference: string | null
  notes: string | null
  supplier: { id: number, name: string } | null
}

export interface ExpenseList extends ErpPaginated<Expense> {
  summary: { total: number, by_category: Partial<Record<ExpenseCategory, number>> }
}

export type LeadStage = 'new' | 'qualified' | 'proposal' | 'won' | 'lost'
export type LeadSource = 'whatsapp' | 'facebook' | 'instagram' | 'storefront' | 'referral' | 'walk_in' | 'event' | 'other'

export interface CrmLead {
  id: number
  title: string
  contact_name: string
  email: string | null
  phone: string | null
  stage: LeadStage
  stage_label: string
  source: LeadSource
  source_label: string
  expected_revenue: number
  probability: number
  weighted_revenue: number
  expected_close_date: string | null
  notes: string | null
  lost_reason: string | null
  closed_at: string | null
  customer_id: number | null
  created_at: string
}

export interface Pipeline {
  stages: { stage: LeadStage, label: string, count: number, expected_revenue: number, weighted_revenue: number }[]
  open_count: number
  weighted_forecast: number
  win_rate: number | null
}

export interface Activity {
  id: number
  event: string
  description: string
  properties?: Record<string, unknown>
  user: string | null
  created_at: string
}

/** GET /shops/{id}/erp/dashboard — sections exist only for installed modules. */
export interface ErpDashboard {
  period_days: number
  currency: string
  modules: ErpModuleKey[]
  sales: {
    revenue: number
    orders_count: number
    average_order: number
    cost_of_goods: number
    gross_margin: number
    to_process: number
  }
  inventory?: {
    stock_value: number
    warehouses_count: number
    out_of_stock_count: number
    reorder_count: number
    critical_count: number
  }
  purchase?: {
    open_count: number
    open_amount: number
    late_count: number
    suppliers_count: number
  }
  invoicing?: {
    receivables: number
    overdue_amount: number
    overdue_count: number
    invoiced: number
    collected: number
  }
  expenses?: {
    total: number
    by_category: Partial<Record<ExpenseCategory, number>>
    net_profit: number
  }
  crm?: Pipeline
  recent_activity: Activity[]
}

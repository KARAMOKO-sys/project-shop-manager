import 'package:flutter/material.dart';

import '../app/config.dart';

/// Typed mirrors of the Laravel API resources (`app/Http/Resources`), parsed defensively.

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.isAdmin,
  });

  final int id;
  final String name;
  final String email;
  final bool isAdmin;

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: _int(json['id']),
    name: _string(json['name']),
    email: _string(json['email']),
    isAdmin: json['is_admin'] == true,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'is_admin': isAdmin,
  };
}

/// Page structure of a storefront. Unknown values fall back to [ShopFormat.classic].
enum ShopFormat {
  classic('classic', 'Classique', 'Bannière, catégories et grille de produits.', Icons.grid_view),
  editorial('editorial', 'Éditorial', 'Mise en page magazine qui raconte vos produits.', Icons.auto_stories_outlined),
  catalog('catalog', 'Catalogue', 'Catalogue dense avec filtres, idéal pour beaucoup de références.', Icons.filter_alt_outlined),
  menu('menu', 'Menu', 'Carte de restaurant par catégories, commande rapide.', Icons.list_alt),
  showcase('showcase', 'Vitrine', 'Grands visuels et beaucoup d’espace pour le haut de gamme.', Icons.diamond_outlined),
  singleProduct('single_product', 'Produit unique', 'Page de vente centrée sur votre produit phare.', Icons.center_focus_strong_outlined);

  const ShopFormat(this.value, this.label, this.description, this.icon);

  final String value;
  final String label;
  final String description;
  final IconData icon;

  static ShopFormat parse(dynamic value) => ShopFormat.values.firstWhere(
    (format) => format.value == value,
    orElse: () => ShopFormat.classic,
  );
}

const themeFonts = [
  'Plus Jakarta Sans',
  'Inter',
  'Poppins',
  'Montserrat',
  'Nunito',
  'DM Sans',
  'Playfair Display',
  'Lora',
  'Space Grotesk',
];

const heroStyles = {'gradient': 'Dégradé', 'image': 'Image', 'minimal': 'Minimaliste'};
const productLayouts = {'grid': 'Grille', 'list': 'Liste', 'masonry': 'Mosaïque'};

class ShopTheme {
  const ShopTheme({
    this.primary = const Color(0xFF0F3D2E),
    this.secondary = const Color(0xFFC8963E),
    this.background = const Color(0xFFFAF7F0),
    this.surface = Colors.white,
    this.text = const Color(0xFF1C1B17),
    this.font = 'Inter',
    this.radius = 12,
    this.heroStyle = 'gradient',
    this.layout = 'grid',
    this.format = ShopFormat.classic,
  });

  final Color primary;
  final Color secondary;
  final Color background;
  final Color surface;
  final Color text;
  final String font;
  final double radius;
  final String heroStyle;
  final String layout;
  final ShopFormat format;

  factory ShopTheme.fromJson(dynamic raw) {
    final json = _map(raw);
    const fallback = ShopTheme();
    return ShopTheme(
      primary: _color(json['primary'], fallback.primary),
      secondary: _color(json['secondary'], fallback.secondary),
      background: _color(json['background'], fallback.background),
      surface: _color(json['surface'], fallback.surface),
      text: _color(json['text'], fallback.text),
      font: themeFonts.contains(json['font']) ? json['font'] as String : fallback.font,
      radius: _radius(json['radius']),
      heroStyle: heroStyles.containsKey(json['hero_style'])
          ? json['hero_style'] as String
          : fallback.heroStyle,
      layout: productLayouts.containsKey(json['layout'])
          ? json['layout'] as String
          : fallback.layout,
      format: ShopFormat.parse(json['format']),
    );
  }

  ShopTheme copyWith({
    Color? primary,
    Color? secondary,
    Color? background,
    Color? surface,
    Color? text,
    String? font,
    double? radius,
    String? heroStyle,
    String? layout,
    ShopFormat? format,
  }) => ShopTheme(
    primary: primary ?? this.primary,
    secondary: secondary ?? this.secondary,
    background: background ?? this.background,
    surface: surface ?? this.surface,
    text: text ?? this.text,
    font: font ?? this.font,
    radius: radius ?? this.radius,
    heroStyle: heroStyle ?? this.heroStyle,
    layout: layout ?? this.layout,
    format: format ?? this.format,
  );

  Map<String, dynamic> toJson() => {
    'primary': colorToHex(primary),
    'secondary': colorToHex(secondary),
    'background': colorToHex(background),
    'surface': colorToHex(surface),
    'text': colorToHex(text),
    'font': font,
    'radius': '${radius.round()}px',
    'hero_style': heroStyle,
    'layout': layout,
    'format': format.value,
  };
}

class Category {
  const Category({required this.id, required this.name, required this.slug});

  final int id;
  final String name;
  final String slug;

  factory Category.fromJson(Map<String, dynamic> json) => Category(
    id: _int(json['id']),
    name: _string(json['name']),
    slug: _string(json['slug']),
  );
}

class Shop {
  const Shop({
    required this.id,
    required this.name,
    required this.slug,
    required this.tagline,
    required this.description,
    required this.sector,
    required this.template,
    required this.theme,
    required this.currency,
    required this.logoUrl,
    required this.status,
    required this.productCount,
    required this.categories,
    required this.url,
  });

  final int id;
  final String name;
  final String slug;
  final String? tagline;
  final String? description;
  final String? sector;
  final String template;
  final ShopTheme theme;
  final String currency;
  final String? logoUrl;
  final String status;
  final int? productCount;
  final List<Category> categories;
  final String url;

  bool get isActive => status == 'active';
  String get initial => name.isEmpty ? 'B' : name.characters.first.toUpperCase();

  factory Shop.fromJson(Map<String, dynamic> json) => Shop(
    id: _int(json['id']),
    name: _string(json['name'], 'Boutique'),
    slug: _string(json['slug']),
    tagline: _nullableString(json['tagline']),
    description: _nullableString(json['description']),
    sector: _nullableString(json['sector']),
    template: _string(json['template']),
    theme: ShopTheme.fromJson(json['theme']),
    currency: _string(json['currency'], 'XOF'),
    logoUrl: resolveMediaUrl(_nullableString(json['logo_url'])),
    status: _string(json['status'], 'active'),
    productCount: json['products_count'] is num ? _int(json['products_count']) : null,
    categories: _list(json['categories'], Category.fromJson),
    url: _string(json['url']),
  );
}

enum ModerationStatus {
  pending('pending', 'En vérification'),
  approved('approved', 'Vérifié'),
  pendingReview('pending_review', 'En vérification'),
  rejected('rejected', 'Refusé');

  const ModerationStatus(this.value, this.label);

  final String value;
  final String label;

  static ModerationStatus parse(dynamic value) => ModerationStatus.values.firstWhere(
    (status) => status.value == value,
    orElse: () => ModerationStatus.pending,
  );
}

class Moderation {
  const Moderation({
    required this.status,
    required this.riskScore,
    required this.reasons,
    required this.note,
    required this.checkedAt,
    required this.engine,
  });

  final ModerationStatus status;
  final int riskScore;
  final List<String> reasons;
  final String? note;
  final DateTime? checkedAt;
  final String? engine;

  factory Moderation.fromJson(dynamic raw) {
    final json = _map(raw);
    return Moderation(
      status: ModerationStatus.parse(json['status']),
      riskScore: _int(json['risk_score']),
      reasons: _strings(json['reasons']),
      note: _nullableString(json['note']),
      checkedAt: _date(json['checked_at']),
      engine: _nullableString(json['engine']),
    );
  }
}

class Product {
  const Product({
    required this.id,
    required this.shopId,
    required this.shopName,
    required this.shopSlug,
    required this.categoryId,
    required this.category,
    required this.name,
    required this.slug,
    required this.sku,
    required this.description,
    required this.price,
    required this.compareAtPrice,
    required this.currency,
    required this.stockQuantity,
    required this.status,
    required this.isFeatured,
    required this.imageUrl,
    required this.tags,
    required this.moderation,
    required this.isPublic,
    required this.createdAt,
  });

  final int id;
  final int shopId;
  final String shopName;
  final String shopSlug;
  final int? categoryId;
  final Category? category;
  final String name;
  final String slug;
  final String? sku;
  final String? description;
  final double price;
  final double? compareAtPrice;
  final String currency;
  final int stockQuantity;
  final String status;
  final bool isFeatured;
  final String? imageUrl;
  final List<String> tags;
  final Moderation moderation;
  final bool isPublic;
  final DateTime? createdAt;

  bool get inStock => stockQuantity > 0;
  bool get isPublished => status == 'published';
  bool get hasDiscount => compareAtPrice != null && compareAtPrice! > price;

  factory Product.fromJson(Map<String, dynamic> json) {
    final shop = _map(json['shop']);
    final category = json['category'];
    return Product(
      id: _int(json['id']),
      shopId: _int(json['shop_id']),
      shopName: _string(shop['name'], 'Boutique'),
      shopSlug: _string(shop['slug']),
      categoryId: json['category_id'] is num ? _int(json['category_id']) : null,
      category: category is Map<String, dynamic> ? Category.fromJson(category) : null,
      name: _string(json['name'], 'Produit'),
      slug: _string(json['slug']),
      sku: _nullableString(json['sku']),
      description: _nullableString(json['description']),
      price: _double(json['price']),
      compareAtPrice: json['compare_at_price'] == null ? null : _double(json['compare_at_price']),
      currency: _string(json['currency'], 'XOF'),
      stockQuantity: _int(json['stock_quantity']),
      status: _string(json['status'], 'published'),
      isFeatured: json['is_featured'] == true,
      imageUrl: resolveMediaUrl(_nullableString(json['image_url'])),
      tags: _strings(json['tags']),
      moderation: Moderation.fromJson(json['moderation']),
      isPublic: json['is_public'] == true,
      createdAt: _date(json['created_at']),
    );
  }
}

enum OrderStatus {
  received('received', 'Reçue'),
  paid('paid', 'Payée'),
  processing('processing', 'En préparation'),
  shipped('shipped', 'Expédiée'),
  delivered('delivered', 'Livrée'),
  cancelled('cancelled', 'Annulée');

  const OrderStatus(this.value, this.label);

  final String value;
  final String label;

  /// Next statuses a merchant may move an order to (mirrors the backend state machine).
  List<OrderStatus> get transitions => switch (this) {
    received => const [paid, processing, cancelled],
    paid => const [processing, cancelled],
    processing => const [shipped, cancelled],
    shipped => const [delivered],
    delivered || cancelled => const [],
  };

  /// Linear progression shown in the customer tracking timeline (cancelled excluded).
  static const timeline = [received, paid, processing, shipped, delivered];

  static OrderStatus parse(dynamic value) => OrderStatus.values.firstWhere(
    (status) => status.value == value,
    orElse: () => OrderStatus.received,
  );
}

enum PaymentMethod {
  cashOnDelivery('cash_on_delivery', 'Paiement à la livraison', 'Réglez en espèces à la réception de votre commande.', Icons.payments_outlined),
  mobileMoney('mobile_money', 'Mobile Money', 'Orange Money, MTN MoMo, Wave, Moov Money…', Icons.phone_android),
  card('card', 'Carte bancaire', 'Visa, Mastercard — paiement sécurisé.', Icons.credit_card);

  const PaymentMethod(this.value, this.label, this.description, this.icon);

  final String value;
  final String label;
  final String description;
  final IconData icon;

  static String labelOf(String value) => PaymentMethod.values
      .firstWhere((method) => method.value == value, orElse: () => cashOnDelivery)
      .label;
}

class Customer {
  const Customer({
    required this.firstName,
    required this.lastName,
    required this.email,
    required this.phone,
  });

  final String firstName;
  final String lastName;
  final String email;
  final String? phone;

  String get fullName => '$firstName $lastName'.trim();

  factory Customer.fromJson(dynamic raw) {
    final json = _map(raw);
    return Customer(
      firstName: _string(json['first_name']),
      lastName: _string(json['last_name']),
      email: _string(json['email']),
      phone: _nullableString(json['phone']),
    );
  }
}

class OrderItem {
  const OrderItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
  });

  final int productId;
  final String productName;
  final int quantity;
  final double unitPrice;
  final double totalPrice;

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
    productId: _int(json['product_id']),
    productName: _string(json['product_name']),
    quantity: _int(json['quantity']),
    unitPrice: _double(json['unit_price']),
    totalPrice: _double(json['total_price']),
  );
}

class Order {
  const Order({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.subtotal,
    required this.shippingAmount,
    required this.discountAmount,
    required this.total,
    required this.currency,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.customer,
    required this.items,
    required this.createdAt,
  });

  final int id;
  final String orderNumber;
  final OrderStatus status;
  final double subtotal;
  final double shippingAmount;
  final double discountAmount;
  final double total;
  final String currency;
  final String paymentMethod;
  final String paymentStatus;
  final Customer customer;
  final List<OrderItem> items;
  final DateTime? createdAt;

  factory Order.fromJson(Map<String, dynamic> json) => Order(
    id: _int(json['id']),
    orderNumber: _string(json['order_number']),
    status: OrderStatus.parse(json['status']),
    subtotal: _double(json['subtotal']),
    shippingAmount: _double(json['shipping_amount']),
    discountAmount: _double(json['discount_amount']),
    total: _double(json['total']),
    currency: _string(json['currency'], 'XOF'),
    paymentMethod: _string(json['payment_method'], 'cash_on_delivery'),
    paymentStatus: _string(json['payment_status']),
    customer: Customer.fromJson(json['customer']),
    items: _list(json['items'], OrderItem.fromJson),
    createdAt: _date(json['created_at']),
  );
}

class RevenueDay {
  const RevenueDay({required this.date, required this.revenue, required this.orders});

  final DateTime date;
  final double revenue;
  final int orders;

  factory RevenueDay.fromJson(Map<String, dynamic> json) => RevenueDay(
    date: _date(json['date']) ?? DateTime.now(),
    revenue: _double(json['revenue']),
    orders: _int(json['orders']),
  );
}

class LowStockProduct {
  const LowStockProduct({required this.id, required this.name, required this.stockQuantity});

  final int id;
  final String name;
  final int stockQuantity;

  factory LowStockProduct.fromJson(Map<String, dynamic> json) => LowStockProduct(
    id: _int(json['id']),
    name: _string(json['name']),
    stockQuantity: _int(json['stock_quantity']),
  );
}

class Analytics {
  const Analytics({
    required this.revenue,
    required this.orders,
    required this.customers,
    required this.products,
    required this.averageOrderValue,
    required this.pendingModeration,
    required this.ordersByStatus,
    required this.lowStockProducts,
    required this.revenueByDay,
  });

  final double revenue;
  final int orders;
  final int customers;
  final int products;
  final double averageOrderValue;
  final int pendingModeration;
  final Map<OrderStatus, int> ordersByStatus;
  final List<LowStockProduct> lowStockProducts;
  final List<RevenueDay> revenueByDay;

  factory Analytics.fromJson(Map<String, dynamic> json) {
    final counts = _map(json['orders_by_status']);
    return Analytics(
      revenue: _double(json['revenue']),
      orders: _int(json['orders']),
      customers: _int(json['customers']),
      products: _int(json['products']),
      averageOrderValue: _double(json['average_order_value']),
      pendingModeration: _int(json['pending_moderation']),
      ordersByStatus: {
        for (final status in OrderStatus.values) status: _int(counts[status.value]),
      },
      lowStockProducts: _list(json['low_stock_products'], LowStockProduct.fromJson),
      revenueByDay: _list(json['revenue_by_day'], RevenueDay.fromJson),
    );
  }
}

class Coupon {
  const Coupon({
    required this.id,
    required this.code,
    required this.type,
    required this.value,
    required this.minOrderAmount,
    required this.startsAt,
    required this.endsAt,
    required this.usageLimit,
    required this.timesUsed,
    required this.status,
    required this.isRedeemable,
  });

  final int id;
  final String code;
  final String type;
  final double value;
  final double minOrderAmount;
  final DateTime? startsAt;
  final DateTime? endsAt;

  /// 0 means unlimited.
  final int usageLimit;
  final int timesUsed;
  final String status;
  final bool isRedeemable;

  bool get isActive => status == 'active';
  bool get isPercentage => type == 'percentage';

  factory Coupon.fromJson(Map<String, dynamic> json) => Coupon(
    id: _int(json['id']),
    code: _string(json['code']),
    type: _string(json['type'], 'percentage'),
    value: _double(json['value']),
    minOrderAmount: _double(json['min_order_amount']),
    startsAt: _date(json['starts_at']),
    endsAt: _date(json['ends_at']),
    usageLimit: _int(json['usage_limit']),
    timesUsed: _int(json['times_used']),
    status: _string(json['status'], 'active'),
    isRedeemable: json['is_redeemable'] == true,
  );
}

class CouponCheck {
  const CouponCheck({
    required this.code,
    required this.type,
    required this.value,
    required this.discountAmount,
  });

  final String code;
  final String type;
  final double value;
  final double discountAmount;

  factory CouponCheck.fromJson(Map<String, dynamic> json) => CouponCheck(
    code: _string(json['code']),
    type: _string(json['type']),
    value: _double(json['value']),
    discountAmount: _double(json['discount_amount']),
  );
}

class ShopTemplate {
  const ShopTemplate({
    required this.key,
    required this.name,
    required this.sector,
    required this.description,
    required this.idealFor,
    required this.theme,
    required this.defaultCategories,
  });

  final String key;
  final String name;
  final String sector;
  final String description;
  final List<String> idealFor;
  final ShopTheme theme;
  final List<String> defaultCategories;

  factory ShopTemplate.fromJson(Map<String, dynamic> json) => ShopTemplate(
    key: _string(json['key']),
    name: _string(json['name']),
    sector: _string(json['sector']),
    description: _string(json['description']),
    idealFor: _strings(json['ideal_for']),
    theme: ShopTheme.fromJson(json['theme']),
    defaultCategories: _strings(json['default_categories']),
  );
}

class HomeData {
  const HomeData({
    required this.featuredProducts,
    required this.shops,
    required this.templates,
    required this.shopCount,
    required this.productCount,
  });

  final List<Product> featuredProducts;
  final List<Shop> shops;
  final List<ShopTemplate> templates;
  final int shopCount;
  final int productCount;

  factory HomeData.fromJson(Map<String, dynamic> json) {
    final stats = _map(json['stats']);
    return HomeData(
      featuredProducts: _list(json['featured_products'], Product.fromJson),
      shops: _list(json['shops'], Shop.fromJson),
      templates: _list(json['templates'], ShopTemplate.fromJson),
      shopCount: _int(stats['shops']),
      productCount: _int(stats['products']),
    );
  }
}

class ChatTurn {
  const ChatTurn({required this.role, required this.content, this.products = const []});

  final String role;
  final String content;
  final List<Product> products;

  bool get isUser => role == 'user';

  Map<String, String> toJson() => {'role': role, 'content': content};
}

class ChatReply {
  const ChatReply({required this.reply, required this.suggestions, required this.products});

  final String reply;
  final List<String> suggestions;
  final List<Product> products;

  factory ChatReply.fromJson(Map<String, dynamic> json) => ChatReply(
    reply: _string(json['reply']),
    suggestions: _strings(json['suggestions']),
    products: _list(json['products'], Product.fromJson),
  );
}

class ShopSetupSuggestion {
  const ShopSetupSuggestion({
    required this.name,
    required this.tagline,
    required this.sector,
    required this.template,
    required this.description,
    required this.categories,
  });

  final String name;
  final String tagline;
  final String sector;
  final String template;
  final String description;
  final List<String> categories;

  factory ShopSetupSuggestion.fromJson(Map<String, dynamic> json) => ShopSetupSuggestion(
    name: _string(json['name']),
    tagline: _string(json['tagline']),
    sector: _string(json['sector']),
    template: _string(json['template']),
    description: _string(json['description']),
    categories: _strings(json['categories']),
  );
}

class ProductCopy {
  const ProductCopy({required this.description, required this.tags});

  final String description;
  final List<String> tags;

  factory ProductCopy.fromJson(Map<String, dynamic> json) => ProductCopy(
    description: _string(json['description']),
    tags: _strings(json['tags']),
  );
}

class Paginated<T> {
  const Paginated({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory Paginated.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) parse,
  ) {
    final items = _list(json['data'], parse);
    final meta = _map(json['meta']);
    return Paginated(
      items: items,
      currentPage: meta.isEmpty ? 1 : _int(meta['current_page']),
      lastPage: meta.isEmpty ? 1 : _int(meta['last_page']),
      total: meta.isEmpty ? items.length : _int(meta['total']),
    );
  }
}

String colorToHex(Color color) {
  final rgb = color.toARGB32() & 0xFFFFFF;
  return '#${rgb.toRadixString(16).padLeft(6, '0').toUpperCase()}';
}

Color? parseHexColor(String? value) {
  if (value == null) return null;
  final hex = value.trim().replaceFirst('#', '');
  final full = hex.length == 3 ? hex.split('').map((c) => '$c$c').join() : hex;
  if (!RegExp(r'^[0-9a-fA-F]{6}$').hasMatch(full)) return null;
  return Color(0xFF000000 | int.parse(full, radix: 16));
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parse) {
  if (value is! List) return const [];
  return value.whereType<Map<String, dynamic>>().map(parse).toList();
}

List<String> _strings(dynamic value) =>
    value is List ? value.map((item) => '$item').toList() : const [];

String _string(dynamic value, [String fallback = '']) =>
    value is String ? value : (value == null ? fallback : '$value');

String? _nullableString(dynamic value) =>
    value is String && value.isNotEmpty ? value : null;

int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

double _double(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

DateTime? _date(dynamic value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;

Color _color(dynamic value, Color fallback) =>
    value is String ? parseHexColor(value) ?? fallback : fallback;

double _radius(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) {
    final match = RegExp(r'^(\d+(?:\.\d+)?)(px|rem)?$').firstMatch(value.trim());
    if (match != null) {
      final number = double.parse(match.group(1)!);
      return match.group(2) == 'rem' ? number * 16 : number;
    }
  }
  return 12;
}

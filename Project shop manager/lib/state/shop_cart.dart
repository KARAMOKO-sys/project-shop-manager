import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';

class CartItem {
  const CartItem({
    required this.productId,
    required this.productSlug,
    required this.shopId,
    required this.shopName,
    required this.shopSlug,
    required this.productName,
    required this.price,
    required this.currency,
    required this.quantity,
    required this.maxQuantity,
    required this.imageUrl,
  });

  final int productId;
  final String productSlug;
  final int shopId;
  final String shopName;
  final String shopSlug;
  final String productName;
  final double price;
  final String currency;
  final int quantity;

  /// Stock known when the product was added; the backend re-checks it at checkout.
  final int maxQuantity;
  final String? imageUrl;

  CartItem withQuantity(int quantity) => CartItem(
    productId: productId,
    productSlug: productSlug,
    shopId: shopId,
    shopName: shopName,
    shopSlug: shopSlug,
    productName: productName,
    price: price,
    currency: currency,
    quantity: quantity,
    maxQuantity: maxQuantity,
    imageUrl: imageUrl,
  );

  Map<String, dynamic> toJson() => {
    'product_id': productId,
    'product_slug': productSlug,
    'shop_id': shopId,
    'shop_name': shopName,
    'shop_slug': shopSlug,
    'product_name': productName,
    'price': price,
    'currency': currency,
    'quantity': quantity,
    'max_quantity': maxQuantity,
    'image_url': imageUrl,
  };

  factory CartItem.fromJson(Map<String, dynamic> json) => CartItem(
    productId: (json['product_id'] as num).toInt(),
    productSlug: json['product_slug'] as String? ?? '',
    shopId: (json['shop_id'] as num).toInt(),
    shopName: json['shop_name'] as String,
    shopSlug: json['shop_slug'] as String,
    productName: json['product_name'] as String,
    price: (json['price'] as num).toDouble(),
    currency: json['currency'] as String,
    quantity: (json['quantity'] as num).toInt(),
    maxQuantity: (json['max_quantity'] as num?)?.toInt() ?? 100,
    imageUrl: json['image_url'] as String?,
  );

  factory CartItem.fromProduct(Product product, Shop? shop, int quantity) => CartItem(
    productId: product.id,
    productSlug: product.slug,
    shopId: product.shopId,
    shopName: shop?.name ?? product.shopName,
    shopSlug: shop?.slug ?? product.shopSlug,
    productName: product.name,
    price: product.price,
    currency: product.currency,
    quantity: quantity,
    maxQuantity: product.stockQuantity.clamp(1, 100),
    imageUrl: product.imageUrl,
  );
}

/// Multi-shop cart persisted on the device. Orders are placed shop by shop, like the
/// per-shop carts of the web storefront.
class ShopCart extends ChangeNotifier {
  ShopCart._(this._preferences, this._items);

  static const _storageKey = 'shopai_mobile_cart';
  final SharedPreferences _preferences;
  final List<CartItem> _items;

  static ShopCart restore(SharedPreferences preferences) {
    final raw = preferences.getString(_storageKey);
    if (raw == null) return ShopCart._(preferences, []);
    try {
      final decoded = jsonDecode(raw);
      final items = decoded is List
          ? decoded.whereType<Map<String, dynamic>>().map(CartItem.fromJson).toList()
          : <CartItem>[];
      return ShopCart._(preferences, items);
    } on Object {
      // A cart saved by an older version is dropped rather than crashing the app.
      return ShopCart._(preferences, []);
    }
  }

  List<CartItem> get items => List.unmodifiable(_items);
  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);

  List<String> get shopSlugs => _items.map((item) => item.shopSlug).toSet().toList();

  List<CartItem> itemsForShop(String slug) =>
      _items.where((item) => item.shopSlug == slug).toList();

  int countForShop(String slug) =>
      itemsForShop(slug).fold(0, (sum, item) => sum + item.quantity);

  double subtotalForShop(String slug) =>
      itemsForShop(slug).fold(0, (sum, item) => sum + item.price * item.quantity);

  /// Adds [quantity] units (capped at the known stock). Requires the product's shop slug,
  /// which storefront payloads always include.
  Future<void> add(Product product, {Shop? shop, int quantity = 1}) async {
    final index = _items.indexWhere((item) => item.productId == product.id);
    if (index == -1) {
      final item = CartItem.fromProduct(product, shop, quantity);
      _items.add(item.withQuantity(quantity.clamp(1, item.maxQuantity)));
    } else {
      final current = _items[index];
      _items[index] = current.withQuantity(
        (current.quantity + quantity).clamp(1, current.maxQuantity),
      );
    }
    await _save();
  }

  Future<void> setQuantity(int productId, int quantity) async {
    final index = _items.indexWhere((item) => item.productId == productId);
    if (index == -1) return;
    if (quantity <= 0) {
      _items.removeAt(index);
    } else {
      _items[index] = _items[index].withQuantity(quantity.clamp(1, _items[index].maxQuantity));
    }
    await _save();
  }

  Future<void> remove(int productId) async {
    _items.removeWhere((item) => item.productId == productId);
    await _save();
  }

  Future<void> clearShop(String slug) async {
    _items.removeWhere((item) => item.shopSlug == slug);
    await _save();
  }

  Future<void> _save() async {
    notifyListeners();
    await _preferences.setString(
      _storageKey,
      jsonEncode(_items.map((item) => item.toJson()).toList()),
    );
  }
}

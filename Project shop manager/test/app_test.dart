import 'dart:convert';

import 'package:app_full_shop/app/config.dart';
import 'package:app_full_shop/app/format.dart';
import 'package:app_full_shop/models/models.dart';
import 'package:app_full_shop/services/api_service.dart';
import 'package:app_full_shop/state/shop_cart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

Map<String, dynamic> productJson({int id = 1, int stock = 5, double price = 4500}) => {
  'id': id,
  'shop_id': 3,
  'shop': {'id': 3, 'name': 'Maison Adjoa', 'slug': 'maison-adjoa'},
  'category_id': 2,
  'category': {'id': 2, 'name': 'Pagnes', 'slug': 'pagnes'},
  'name': 'Pagne wax',
  'slug': 'pagne-wax-$id',
  'sku': null,
  'description': 'Coton 100 %',
  'price': price,
  'compare_at_price': 6000,
  'currency': 'XOF',
  'stock_quantity': stock,
  'status': 'published',
  'is_featured': true,
  'image_url': 'http://localhost:8000/storage/shops/3/wax.jpg',
  'tags': ['wax', 'mode'],
  'moderation': {'status': 'approved', 'risk_score': 4, 'reasons': [], 'note': null, 'checked_at': null, 'engine': 'local'},
  'is_public': true,
  'created_at': '2026-10-06T10:00:00.000000Z',
};

void main() {
  group('format', () {
    test('formats CFA prices like the web', () {
      expect(formatPrice(4500, 'XOF'), '4 500 FCFA');
      expect(formatPrice(1250000, 'XAF'), '1 250 000 FCFA');
      expect(formatPrice(12.5, 'EUR'), '12,5 €');
    });

    test('formats dates in French', () {
      expect(formatShortDate(DateTime(2026, 10, 7)), '7 oct. 2026');
      expect(isoDate(DateTime(2026, 1, 3)), '2026-01-03');
    });
  });

  group('resolveMediaUrl', () {
    test('rewrites loopback storage URLs to the API host', () {
      expect(
        resolveMediaUrl('http://localhost:8000/storage/a.jpg', baseUrl: 'http://10.0.2.2:8000/api/v1'),
        'http://10.0.2.2:8000/storage/a.jpg',
      );
    });

    test('keeps public URLs untouched', () {
      expect(resolveMediaUrl('https://cdn.example.com/a.jpg'), 'https://cdn.example.com/a.jpg');
      expect(resolveMediaUrl(''), isNull);
    });
  });

  group('models', () {
    test('parses a product resource', () {
      final product = Product.fromJson(productJson());
      expect(product.shopSlug, 'maison-adjoa');
      expect(product.category?.name, 'Pagnes');
      expect(product.hasDiscount, isTrue);
      expect(product.moderation.status, ModerationStatus.approved);
      expect(product.imageUrl, startsWith('http://10.0.2.2:8000/'));
    });

    test('parses a shop theme with fallbacks', () {
      final theme = ShopTheme.fromJson({'primary': '#123456', 'radius': '1rem', 'format': 'menu', 'font': 'Comic'});
      expect(theme.primary, const Color(0xFF123456));
      expect(theme.radius, 16);
      expect(theme.format, ShopFormat.menu);
      expect(theme.font, 'Inter');
      expect(theme.toJson()['primary'], '#123456');
      expect(ShopFormat.parse('unknown'), ShopFormat.classic);
    });

    test('order status transitions mirror the backend', () {
      expect(OrderStatus.received.transitions, [OrderStatus.paid, OrderStatus.processing, OrderStatus.cancelled]);
      expect(OrderStatus.delivered.transitions, isEmpty);
    });

    test('parses paginated collections', () {
      final page = Paginated.fromJson({
        'data': [productJson()],
        'meta': {'current_page': 1, 'last_page': 3, 'total': 60},
      }, Product.fromJson);
      expect(page.items, hasLength(1));
      expect(page.hasMore, isTrue);
    });
  });

  group('ApiService', () {
    test('sends the bearer token and parses analytics', () async {
      late http.Request sent;
      final api = ApiService(
        baseUrl: 'http://api.test/api/v1',
        client: MockClient((request) async {
          sent = request;
          return http.Response(
            jsonEncode({
              'data': {
                'revenue': 9000,
                'orders': 2,
                'customers': 2,
                'products': 4,
                'average_order_value': 4500,
                'pending_moderation': 1,
                'orders_by_status': {'delivered': 2},
                'low_stock_products': [],
                'revenue_by_day': [{'date': '2026-10-06', 'revenue': 9000, 'orders': 2}],
              },
            }),
            200,
          );
        }),
      )..token = 'abc';
      final analytics = await api.getAnalytics(3);
      expect(sent.url.toString(), 'http://api.test/api/v1/shops/3/analytics');
      expect(sent.headers['Authorization'], 'Bearer abc');
      expect(analytics.ordersByStatus[OrderStatus.delivered], 2);
      expect(analytics.revenueByDay.single.revenue, 9000);
    });

    test('maps Laravel validation errors per field', () async {
      final api = ApiService(
        baseUrl: 'http://api.test/api/v1',
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'message': 'Invalid',
              'errors': {
                'customer.email': ['Adresse e-mail invalide.'],
                'items.0.quantity': ['Stock insuffisant.'],
              },
            }),
            422,
          ),
        ),
      );
      final error = await api
          .checkout(shopSlug: 'maison-adjoa', customer: const {}, items: const [], paymentMethod: 'card')
          .then<ApiException?>((_) => null, onError: (Object error) => error as ApiException);
      expect(error!.isValidation, isTrue);
      expect(error.fieldError('customer.email'), 'Adresse e-mail invalide.');
      expect(error.fieldError('items'), 'Stock insuffisant.');
    });

    test('notifies the session on 401 for authenticated calls', () async {
      var expired = false;
      final api = ApiService(
        baseUrl: 'http://api.test/api/v1',
        client: MockClient((_) async => http.Response('{"message":"Unauthenticated."}', 401)),
      )
        ..token = 'old'
        ..onUnauthorized = () => expired = true;
      await expectLater(api.getMyShops(), throwsA(isA<ApiException>()));
      expect(expired, isTrue);
    });
  });

  group('ShopCart', () {
    setUp(() => SharedPreferences.setMockInitialValues({}));

    test('groups items by shop, caps quantity to stock and persists', () async {
      final preferences = await SharedPreferences.getInstance();
      final cart = ShopCart.restore(preferences);
      await cart.add(Product.fromJson(productJson(stock: 2)), quantity: 5);
      await cart.add(Product.fromJson(productJson(id: 2, price: 1000)));
      expect(cart.itemCount, 3);
      expect(cart.shopSlugs, ['maison-adjoa']);
      expect(cart.subtotalForShop('maison-adjoa'), 2 * 4500 + 1000);

      final restored = ShopCart.restore(preferences);
      expect(restored.itemCount, 3);
      await restored.clearShop('maison-adjoa');
      expect(restored.items, isEmpty);
    });
  });
}

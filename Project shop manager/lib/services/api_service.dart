import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../app/config.dart';
import '../models/models.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;

  /// Laravel 422 errors flattened to `{field: firstMessage}`.
  final Map<String, String> fieldErrors;

  bool get isNetworkError => statusCode == null;
  bool get isNotFound => statusCode == 404;
  bool get isValidation => statusCode == 422;

  /// First message of [field] or of any nested key (`items.0.quantity` for `items`).
  String? fieldError(String field) {
    if (fieldErrors[field] case final message?) return message;
    for (final entry in fieldErrors.entries) {
      if (entry.key.startsWith('$field.')) return entry.value;
    }
    return null;
  }

  @override
  String toString() => message;
}

/// Client of the `e-shop-full` Laravel API (`routes/api.php`, prefix `/api/v1`).
class ApiService {
  ApiService({http.Client? client, String baseUrl = apiBaseUrl})
    : _client = client ?? http.Client(),
      _baseUri = Uri.parse(baseUrl);

  final http.Client _client;
  final Uri _baseUri;

  /// Sanctum bearer token of the signed-in merchant.
  String? token;

  /// Called when an authenticated request is rejected with 401 (expired or revoked token).
  void Function()? onUnauthorized;

  // ---------- Platform ----------

  Future<HomeData> getHome() async =>
      HomeData.fromJson(_data(await _request('home')));

  Future<List<ShopTemplate>> getTemplates() async =>
      _dataList(await _request('templates'), ShopTemplate.fromJson);

  Future<ChatReply> platformChat(String message, List<ChatTurn> history) async =>
      ChatReply.fromJson(
        _data(
          await _request(
            'chat',
            method: 'POST',
            body: {'message': message, 'history': _history(history)},
          ),
        ),
      );

  // ---------- Storefront ----------

  Future<Shop> getStorefront(String slug) async =>
      Shop.fromJson(_data(await _request('storefront/${_segment(slug)}')));

  Future<Paginated<Product>> getStorefrontProducts(
    String slug, {
    String? category,
    String? search,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (category != null && category.isNotEmpty) query['category'] = category;
    if (search != null && search.trim().isNotEmpty) query['search'] = search.trim();
    final json = await _request('storefront/${_segment(slug)}/products', query: query);
    return Paginated.fromJson(json, Product.fromJson);
  }

  Future<Product> getStorefrontProduct(String slug, String productSlug) async =>
      Product.fromJson(
        _data(await _request('storefront/${_segment(slug)}/products/${_segment(productSlug)}')),
      );

  Future<List<Product>> getRecommendations(String slug, String productSlug) async =>
      _dataList(
        await _request(
          'storefront/${_segment(slug)}/products/${_segment(productSlug)}/recommendations',
        ),
        Product.fromJson,
      );

  Future<Order> trackOrder(String slug, String orderNumber, String email) async =>
      Order.fromJson(
        _data(
          await _request(
            'storefront/${_segment(slug)}/orders/${_segment(orderNumber)}',
            query: {'email': email},
          ),
        ),
      );

  Future<CouponCheck> checkCoupon(String slug, String code, double subtotal) async =>
      CouponCheck.fromJson(
        _data(
          await _request(
            'storefront/${_segment(slug)}/coupons/check',
            method: 'POST',
            body: {'code': code, 'subtotal': subtotal},
          ),
        ),
      );

  Future<Order> checkout({
    required String shopSlug,
    required Map<String, String> customer,
    required List<Map<String, int>> items,
    required String paymentMethod,
    String? couponCode,
    String? notes,
  }) async {
    final json = await _request(
      'storefront/${_segment(shopSlug)}/checkout',
      method: 'POST',
      body: {
        'customer': customer,
        'items': items,
        'payment_method': paymentMethod,
        if (couponCode != null && couponCode.isNotEmpty) 'coupon_code': couponCode,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Order.fromJson(_data(json));
  }

  Future<ChatReply> shopChat(String slug, String message, List<ChatTurn> history) async =>
      ChatReply.fromJson(
        _data(
          await _request(
            'storefront/${_segment(slug)}/chat',
            method: 'POST',
            body: {'message': message, 'history': _history(history)},
          ),
        ),
      );

  // ---------- Auth ----------

  Future<(User, String)> login(String email, String password) =>
      _authenticate('auth/login', {'email': email, 'password': password});

  Future<(User, String)> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) => _authenticate('auth/register', {
    'name': name,
    'email': email,
    'password': password,
    'password_confirmation': passwordConfirmation,
  });

  Future<User> me() async => User.fromJson(_data(await _request('auth/me', auth: true)));

  Future<void> logout() => _request('auth/logout', method: 'POST', auth: true);

  // ---------- AI assistants ----------

  Future<ShopSetupSuggestion> suggestShopSetup(String prompt) async =>
      ShopSetupSuggestion.fromJson(
        _data(await _request('ai/shop-setup', method: 'POST', auth: true, body: {'prompt': prompt})),
      );

  Future<ProductCopy> generateProductDescription({
    required String name,
    String? features,
    required String tone,
  }) async => ProductCopy.fromJson(
    _data(
      await _request(
        'ai/product-description',
        method: 'POST',
        auth: true,
        body: {
          'name': name,
          if (features != null && features.trim().isNotEmpty) 'features': features.trim(),
          'tone': tone,
        },
      ),
    ),
  );

  // ---------- Merchant: shops ----------

  Future<List<Shop>> getMyShops() async =>
      _dataList(await _request('shops', auth: true), Shop.fromJson);

  Future<Shop> createShop(Map<String, dynamic> body) async =>
      Shop.fromJson(_data(await _request('shops', method: 'POST', auth: true, body: body)));

  Future<Shop> getShop(int shopId) async =>
      Shop.fromJson(_data(await _request('shops/$shopId', auth: true)));

  Future<Shop> updateShop(int shopId, Map<String, dynamic> body) async => Shop.fromJson(
    _data(await _request('shops/$shopId', method: 'PUT', auth: true, body: body)),
  );

  Future<void> deleteShop(int shopId) =>
      _request('shops/$shopId', method: 'DELETE', auth: true);

  Future<Analytics> getAnalytics(int shopId) async =>
      Analytics.fromJson(_data(await _request('shops/$shopId/analytics', auth: true)));

  Future<List<Category>> getCategories(int shopId) async =>
      _dataList(await _request('shops/$shopId/categories', auth: true), Category.fromJson);

  Future<Category> createCategory(int shopId, String name) async => Category.fromJson(
    _data(
      await _request('shops/$shopId/categories', method: 'POST', auth: true, body: {'name': name}),
    ),
  );

  // ---------- Merchant: products ----------

  Future<Paginated<Product>> getShopProducts(int shopId, {int page = 1}) async =>
      Paginated.fromJson(
        await _request('shops/$shopId/products', auth: true, query: {'page': '$page'}),
        Product.fromJson,
      );

  Future<Product> saveProduct(int shopId, Map<String, dynamic> body, {int? productId}) async =>
      Product.fromJson(
        _data(
          await _request(
            productId == null ? 'shops/$shopId/products' : 'shops/$shopId/products/$productId',
            method: productId == null ? 'POST' : 'PUT',
            auth: true,
            body: body,
          ),
        ),
      );

  Future<void> deleteProduct(int shopId, int productId) =>
      _request('shops/$shopId/products/$productId', method: 'DELETE', auth: true);

  Future<Product> remoderateProduct(int shopId, int productId) async => Product.fromJson(
    _data(await _request('shops/$shopId/products/$productId/moderate', method: 'POST', auth: true)),
  );

  /// Uploads a JPG/PNG/WebP (max 4 MB) and returns its public URL.
  Future<String> uploadImage(int shopId, List<int> bytes, String filename) async {
    final request = http.MultipartRequest('POST', _uri('shops/$shopId/uploads'))
      ..files.add(http.MultipartFile.fromBytes('image', bytes, filename: filename));
    final json = await _send(request, auth: true);
    final url = _data(json)['url'];
    if (url is! String) throw const ApiException('Le serveur n’a pas renvoyé l’adresse de l’image.');
    return url;
  }

  // ---------- Merchant: orders & coupons ----------

  Future<Paginated<Order>> getShopOrders(int shopId, {int page = 1}) async =>
      Paginated.fromJson(
        await _request('shops/$shopId/orders', auth: true, query: {'page': '$page'}),
        Order.fromJson,
      );

  Future<Order> updateOrderStatus(int shopId, int orderId, OrderStatus status) async =>
      Order.fromJson(
        _data(
          await _request(
            'shops/$shopId/orders/$orderId',
            method: 'PATCH',
            auth: true,
            body: {'status': status.value},
          ),
        ),
      );

  Future<List<Coupon>> getCoupons(int shopId) async =>
      _dataList(await _request('shops/$shopId/coupons', auth: true), Coupon.fromJson);

  Future<Coupon> createCoupon(int shopId, Map<String, dynamic> body) async => Coupon.fromJson(
    _data(await _request('shops/$shopId/coupons', method: 'POST', auth: true, body: body)),
  );

  Future<Coupon> updateCoupon(int shopId, int couponId, Map<String, dynamic> body) async =>
      Coupon.fromJson(
        _data(
          await _request('shops/$shopId/coupons/$couponId', method: 'PUT', auth: true, body: body),
        ),
      );

  Future<void> deleteCoupon(int shopId, int couponId) =>
      _request('shops/$shopId/coupons/$couponId', method: 'DELETE', auth: true);

  // ---------- Admin moderation ----------

  Future<Paginated<Product>> getModerationQueue({
    String status = 'pending_review',
    int page = 1,
  }) async => Paginated.fromJson(
    await _request('admin/moderation', auth: true, query: {'status': status, 'page': '$page'}),
    Product.fromJson,
  );

  Future<Product> decideModeration(int productId, {required bool approve, String? note}) async =>
      Product.fromJson(
        _data(
          await _request(
            'admin/moderation/$productId',
            method: 'POST',
            auth: true,
            body: {
              'decision': approve ? 'approve' : 'reject',
              if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
            },
          ),
        ),
      );

  // ---------- Plumbing ----------

  Future<(User, String)> _authenticate(String path, Map<String, String> body) async {
    final data = _data(await _request(path, method: 'POST', body: body));
    final user = data['user'];
    final token = data['token'];
    if (user is! Map<String, dynamic> || token is! String) {
      throw const ApiException('Le serveur a renvoyé une réponse invalide.');
    }
    return (User.fromJson(user), token);
  }

  List<Map<String, String>> _history(List<ChatTurn> history) =>
      history.length > 20
          ? history.sublist(history.length - 20).map((turn) => turn.toJson()).toList()
          : history.map((turn) => turn.toJson()).toList();

  String _segment(String value) => Uri.encodeComponent(value);

  Uri _uri(String path, [Map<String, String>? query]) {
    final basePath = _baseUri.path.endsWith('/')
        ? _baseUri.path.substring(0, _baseUri.path.length - 1)
        : _baseUri.path;
    return _baseUri.replace(
      path: '$basePath/$path',
      queryParameters: query == null || query.isEmpty ? null : query,
    );
  }

  Future<Map<String, dynamic>> _request(
    String path, {
    String method = 'GET',
    Map<String, String>? query,
    Object? body,
    bool auth = false,
  }) {
    final request = http.Request(method, _uri(path, query));
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }
    return _send(request, auth: auth || token != null);
  }

  Future<Map<String, dynamic>> _send(http.BaseRequest request, {required bool auth}) async {
    request.headers['Accept'] = 'application/json';
    if (auth && token != null) request.headers['Authorization'] = 'Bearer $token';
    final http.Response response;
    try {
      final streamed = await _client.send(request).timeout(const Duration(seconds: 30));
      response = await http.Response.fromStream(streamed);
    } on TimeoutException {
      throw const ApiException('Le serveur met trop de temps à répondre. Réessayez dans un instant.');
    } on Exception {
      throw const ApiException(
        'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.',
      );
    }

    Map<String, dynamic> decoded = const {};
    if (response.body.isNotEmpty) {
      try {
        final json = jsonDecode(utf8.decode(response.bodyBytes));
        if (json is Map<String, dynamic>) decoded = json;
      } on FormatException {
        if (response.statusCode < 300) {
          throw const ApiException('Le serveur a renvoyé une réponse illisible.');
        }
      }
    }

    if (response.statusCode >= 200 && response.statusCode < 300) return decoded;

    if (response.statusCode == 401 && request.headers.containsKey('Authorization')) {
      onUnauthorized?.call();
    }
    throw _error(decoded, response.statusCode);
  }

  ApiException _error(Map<String, dynamic> json, int statusCode) {
    final fieldErrors = <String, String>{};
    final errors = json['errors'];
    if (errors is Map) {
      errors.forEach((field, messages) {
        if (messages is List && messages.isNotEmpty) {
          fieldErrors['$field'] = '${messages.first}';
        } else if (messages is String) {
          fieldErrors['$field'] = messages;
        }
      });
    }
    final serverMessage = json['message'] is String ? json['message'] as String : '';
    final message = switch (statusCode) {
      422 when fieldErrors.isNotEmpty => fieldErrors.values.first,
      401 => 'Votre session a expiré. Reconnectez-vous.',
      403 => 'Vous n’avez pas accès à cette ressource.',
      404 => 'Élément introuvable.',
      429 => 'Trop de tentatives. Patientez une minute avant de réessayer.',
      >= 500 => 'Le serveur a rencontré une erreur. Réessayez plus tard.',
      _ when serverMessage.isNotEmpty => serverMessage,
      _ => 'Une erreur est survenue (HTTP $statusCode).',
    };
    return ApiException(message, statusCode: statusCode, fieldErrors: fieldErrors);
  }

  Map<String, dynamic> _data(Map<String, dynamic> json) {
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw const ApiException('Le serveur a renvoyé une réponse invalide.');
    }
    return data;
  }

  List<T> _dataList<T>(Map<String, dynamic> json, T Function(Map<String, dynamic>) parse) {
    final data = json['data'];
    if (data is! List) throw const ApiException('Le serveur a renvoyé une réponse invalide.');
    return data.whereType<Map<String, dynamic>>().map(parse).toList();
  }
}

/// User-facing message for any error caught in the UI.
String errorText(Object error, [String fallback = 'Une erreur est survenue. Veuillez réessayer.']) =>
    error is ApiException ? error.message : fallback;

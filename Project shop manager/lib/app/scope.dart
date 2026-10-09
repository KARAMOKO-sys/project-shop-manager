import 'package:flutter/widgets.dart';

import '../services/api_service.dart';
import '../state/session.dart';
import '../state/shop_cart.dart';

/// Gives every screen access to the shared API client, merchant session and cart.
class AppScope extends InheritedWidget {
  const AppScope({
    required this.api,
    required this.session,
    required this.cart,
    required super.child,
    super.key,
  });

  final ApiService api;
  final Session session;
  final ShopCart cart;

  static AppScope of(BuildContext context) {
    final scope = context.getInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope introuvable dans l’arbre de widgets.');
    return scope!;
  }

  @override
  bool updateShouldNotify(AppScope oldWidget) =>
      api != oldWidget.api || session != oldWidget.session || cart != oldWidget.cart;
}

extension AppScopeContext on BuildContext {
  ApiService get api => AppScope.of(this).api;
  Session get session => AppScope.of(this).session;
  ShopCart get cart => AppScope.of(this).cart;
}

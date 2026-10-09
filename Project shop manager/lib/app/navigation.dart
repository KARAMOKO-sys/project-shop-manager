import 'package:flutter/material.dart';

import '../models/models.dart';
import '../screens/storefront/product_screen.dart';
import '../screens/storefront/shop_screen.dart';

Future<T?> pushScreen<T>(BuildContext context, Widget screen) =>
    Navigator.of(context).push<T>(MaterialPageRoute(builder: (_) => screen));

Future<void> openShop(BuildContext context, String slug) => pushScreen(context, ShopScreen(slug: slug));

Future<void> openProduct(BuildContext context, Product product, {String? shopSlug}) => pushScreen(
  context,
  ProductScreen(shopSlug: shopSlug ?? product.shopSlug, productSlug: product.slug, initial: product),
);

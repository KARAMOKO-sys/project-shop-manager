import 'dart:async';

import 'package:flutter/material.dart';

import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../../widgets/product_card.dart';
import 'cart_screen.dart';

/// Product page (`/boutique/{slug}/produit/{productSlug}`) with recommendations.
class ProductScreen extends StatefulWidget {
  const ProductScreen({required this.shopSlug, required this.productSlug, this.initial, super.key});

  final String shopSlug;
  final String productSlug;

  /// Product already known from a list, shown while the fresh copy loads.
  final Product? initial;

  @override
  State<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends State<ProductScreen> {
  Shop? _shop;
  Product? _product;
  List<Product> _recommendations = const [];
  Object? _error;
  int _quantity = 1;

  @override
  void initState() {
    super.initState();
    _product = widget.initial;
    _load();
  }

  Future<void> _load() async {
    final api = context.api;
    setState(() => _error = null);
    // Recommendations are optional: any failure simply hides the section.
    api
        .getRecommendations(widget.shopSlug, widget.productSlug)
        .then((items) {
          if (mounted) setState(() => _recommendations = items);
        })
        .catchError((Object _) {});
    try {
      final (shop, product) = await (
        api.getStorefront(widget.shopSlug),
        api.getStorefrontProduct(widget.shopSlug, widget.productSlug),
      ).wait;
      if (!mounted) return;
      setState(() {
        _shop = shop;
        _product = product;
        _quantity = _quantity.clamp(1, product.stockQuantity.clamp(1, 100));
      });
    } on ParallelWaitError<(Shop?, Product?), (AsyncError?, AsyncError?)> catch (error) {
      if (!mounted) return;
      setState(() => _error = error.errors.$2?.error ?? error.errors.$1?.error ?? error);
    }
  }

  Future<void> _addToCart(Product product, int quantity) async {
    await context.cart.add(product, shop: _shop, quantity: quantity);
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(quantity > 1 ? '$quantity × « ${product.name} » ajouté au panier.' : '« ${product.name} » ajouté au panier.'),
          action: SnackBarAction(
            label: 'Voir',
            textColor: gold,
            onPressed: () => showShopCartSheet(context, shopSlug: widget.shopSlug, accentColor: _shop?.theme.primary),
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final shopTheme = _shop?.theme;
    final page = _buildPage();
    return shopTheme == null ? page : Theme(data: buildShopTheme(context, shopTheme), child: page);
  }

  Widget _buildPage() {
    final product = _product;
    if (product == null) {
      if (_error != null) {
        final notFound = _error is ApiException && (_error! as ApiException).isNotFound;
        return Scaffold(
          appBar: AppBar(),
          body: MessageState(
            icon: Icons.search_off,
            title: 'Produit introuvable',
            message: notFound ? 'Ce produit n’existe plus ou n’est pas encore disponible.' : errorText(_error!),
            onRetry: notFound ? null : _load,
            action: notFound
                ? ElevatedButton(onPressed: () => openShop(context, widget.shopSlug), child: const Text('Retour à la boutique'))
                : null,
          ),
        );
      }
      return const Scaffold(body: LoadingView(text: 'Chargement du produit…'));
    }

    final accent = _shop?.theme.primary ?? forest;
    final text = _shop?.theme.text ?? ink;
    final maxQuantity = product.stockQuantity.clamp(1, 100);

    return Scaffold(
      appBar: AppBar(
        title: Text(_shop?.name ?? product.shopName, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            tooltip: 'Voir la boutique',
            onPressed: () => openShop(context, widget.shopSlug),
            icon: const Icon(Icons.storefront_outlined),
          ),
          ShopCartButton(shopSlug: widget.shopSlug, accentColor: accent),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          decoration: BoxDecoration(
            color: _shop?.theme.surface ?? Colors.white,
            border: const Border(top: BorderSide(color: border)),
          ),
          child: product.inStock
              ? Row(
                  children: [
                    Container(
                      decoration: BoxDecoration(border: Border.all(color: border), borderRadius: BorderRadius.circular(30)),
                      child: Row(
                        children: [
                          IconButton(
                            tooltip: 'Diminuer la quantité',
                            onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null,
                            icon: const Icon(Icons.remove),
                          ),
                          SizedBox(
                            width: 26,
                            child: Text('$_quantity', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                          ),
                          IconButton(
                            tooltip: 'Augmenter la quantité',
                            onPressed: _quantity < maxQuantity ? () => setState(() => _quantity++) : null,
                            icon: const Icon(Icons.add),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () => _addToCart(product, _quantity),
                        icon: const Icon(Icons.shopping_bag_outlined),
                        label: const Text('Ajouter au panier'),
                      ),
                    ),
                  ],
                )
              : const ElevatedButton(onPressed: null, child: Text('Épuisé')),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            ProductImage(url: product.imageUrl, color: accent, aspectRatio: 1),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 18, 18, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (product.category != null)
                    Text(
                      product.category!.name.toUpperCase(),
                      style: TextStyle(color: _shop?.theme.secondary ?? goldInk, fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: 1.2),
                    ),
                  const SizedBox(height: 4),
                  Text(product.name, style: TextStyle(color: text, fontSize: 25, fontWeight: FontWeight.w800, height: 1.15)),
                  const SizedBox(height: 10),
                  PriceTag(amount: product.price, currency: product.currency, compareAt: product.compareAtPrice, color: accent, fontSize: 22),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      if (product.inStock)
                        const StatusPill(label: 'En stock', tone: Tone.success, icon: Icons.check)
                      else
                        const StatusPill(label: 'Épuisé'),
                      if (product.moderation.status == ModerationStatus.approved)
                        const StatusPill(label: 'Produit vérifié', tone: Tone.primary, icon: Icons.verified_user_outlined),
                    ],
                  ),
                  if (product.description != null) ...[
                    const SizedBox(height: 16),
                    Text(product.description!, style: TextStyle(color: text.withValues(alpha: 0.75), height: 1.55)),
                  ],
                  if (product.tags.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        for (final tag in product.tags)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(color: tertiary, borderRadius: BorderRadius.circular(20)),
                            child: Text('#$tag', style: const TextStyle(color: muted, fontSize: 12)),
                          ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 18),
                  const Divider(),
                  const SizedBox(height: 10),
                  for (final (icon, label) in const [
                    (Icons.local_shipping_outlined, 'Livraison rapide dans votre ville'),
                    (Icons.phone_android, 'Paiement Mobile Money ou à la livraison'),
                    (Icons.chat_bubble_outline, 'Une question ? Notre assistant répond 24h/24'),
                  ])
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: [
                          Icon(icon, size: 18, color: muted),
                          const SizedBox(width: 10),
                          Expanded(child: Text(label, style: const TextStyle(color: muted, fontSize: 13))),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            if (_recommendations.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 12),
                child: Text('Les clients ont aussi acheté', style: TextStyle(color: text, fontSize: 20, fontWeight: FontWeight.w800)),
              ),
              SizedBox(
                height: 290,
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  scrollDirection: Axis.horizontal,
                  itemCount: _recommendations.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 12),
                  itemBuilder: (context, index) {
                    final item = _recommendations[index];
                    return SizedBox(
                      width: 170,
                      child: ProductCard(
                        product: item,
                        accentColor: accent,
                        onTap: () => openProduct(context, item, shopSlug: widget.shopSlug),
                        onAdd: (product) => _addToCart(product, 1),
                      ),
                    );
                  },
                ),
              ),
            ],
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

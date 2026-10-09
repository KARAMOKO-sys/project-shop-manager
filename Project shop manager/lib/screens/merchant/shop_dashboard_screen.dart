import 'package:flutter/material.dart';

import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../storefront/shop_screen.dart';
import 'tabs/appearance_tab.dart';
import 'tabs/coupons_tab.dart';
import 'tabs/orders_tab.dart';
import 'tabs/overview_tab.dart';
import 'tabs/products_tab.dart';
import 'tabs/settings_tab.dart';

/// Management of one shop, mirroring `pages/dashboard/boutiques/[id]/index.vue`.
///
/// Pops `true` when the shop has been deleted from the settings tab.
class ShopDashboardScreen extends StatefulWidget {
  const ShopDashboardScreen({required this.shopId, this.initialShop, super.key});

  final int shopId;
  final Shop? initialShop;

  @override
  State<ShopDashboardScreen> createState() => _ShopDashboardScreenState();
}

class _ShopDashboardScreenState extends State<ShopDashboardScreen> {
  static const _tabs = [
    (label: 'Vue d’ensemble', icon: Icons.speed_outlined),
    (label: 'Produits', icon: Icons.inventory_2_outlined),
    (label: 'Commandes', icon: Icons.receipt_long_outlined),
    (label: 'Promotions', icon: Icons.local_offer_outlined),
    (label: 'Apparence', icon: Icons.palette_outlined),
    (label: 'Paramètres', icon: Icons.settings_outlined),
  ];

  late Shop? _shop = widget.initialShop;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    // Always fetch the latest version, even when an initial shop was provided.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final shop = await context.api.getShop(widget.shopId);
      if (!mounted) return;
      setState(() => _shop = shop);
    } on Object catch (error) {
      if (!mounted) return;
      if (_shop == null) {
        setState(() => _error = errorText(error, 'Cette boutique n’existe pas ou ne vous appartient pas.'));
      } else {
        showToast(context, errorText(error, 'La boutique n’a pas pu être actualisée.'), error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onShopUpdated(Shop shop) => setState(() => _shop = shop);

  void _onDeleted() {
    if (mounted) Navigator.pop(context, true);
  }

  void _openStorefront(Shop shop) {
    Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ShopScreen(slug: shop.slug)));
  }

  @override
  Widget build(BuildContext context) {
    final shop = _shop;
    if (shop == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Ma boutique')),
        body: _error != null
            ? MessageState(
                title: 'Boutique introuvable',
                message: _error,
                icon: Icons.store_mall_directory_outlined,
                onRetry: _load,
              )
            : const LoadingView(text: 'Chargement de la boutique…'),
      );
    }

    return DefaultTabController(
      length: _tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Ma boutique'),
          actions: [
            if (_loading)
              const Padding(
                padding: EdgeInsets.only(right: 8),
                child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
              ),
          ],
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(128),
            child: Column(
              children: [
                _Header(shop: shop, onOpenStorefront: () => _openStorefront(shop)),
                TabBar(
                  isScrollable: true,
                  tabs: [
                    for (final tab in _tabs)
                      Tab(
                        height: 46,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [Icon(tab.icon, size: 18), const SizedBox(width: 6), Text(tab.label)],
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
        body: TabBarView(
          children: [
            OverviewTab(key: ValueKey('overview-${shop.id}'), shop: shop),
            ProductsTab(key: ValueKey('products-${shop.id}'), shop: shop),
            OrdersTab(key: ValueKey('orders-${shop.id}'), shop: shop),
            CouponsTab(key: ValueKey('coupons-${shop.id}'), shop: shop),
            AppearanceTab(key: ValueKey('appearance-${shop.id}'), shop: shop, onShopUpdated: _onShopUpdated),
            SettingsTab(
              key: ValueKey('settings-${shop.id}'),
              shop: shop,
              onShopUpdated: _onShopUpdated,
              onDeleted: _onDeleted,
            ),
          ],
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.shop, required this.onOpenStorefront});

  final Shop shop;
  final VoidCallback onOpenStorefront;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 82,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
        child: Row(
          children: [
            ShopLogo(name: shop.name, logoUrl: shop.logoUrl, color: shop.theme.primary, size: 52),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    shop.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: headingStyle(fontSize: 18),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      StatusPill(
                        label: shop.isActive ? 'En ligne' : 'Hors ligne',
                        tone: shop.isActive ? Tone.success : Tone.neutral,
                      ),
                      if (shop.tagline != null) ...[
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            shop.tagline!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: muted, fontSize: 12.5),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            OutlinedButton.icon(
              onPressed: onOpenStorefront,
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(40, 38),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                visualDensity: VisualDensity.compact,
              ),
              icon: const Icon(Icons.storefront_outlined, size: 18),
              label: const Text('Voir ma boutique'),
            ),
          ],
        ),
      ),
    );
  }
}

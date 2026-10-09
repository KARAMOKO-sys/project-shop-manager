import 'package:flutter/material.dart';

import '../../app/format.dart';
import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../main.dart';
import '../../state/shop_cart.dart';
import '../../widgets/common.dart';
import 'checkout_screen.dart';
import 'product_screen.dart';

/// Cart tab: one section per shop, since orders are placed shop by shop.
class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final cart = context.cart;
    return Scaffold(
      appBar: AppBar(title: const Text('Mon panier')),
      body: ListenableBuilder(
        listenable: cart,
        builder: (context, _) {
          final slugs = cart.shopSlugs;
          if (slugs.isEmpty) {
            return MessageState(
              icon: Icons.shopping_bag_outlined,
              title: 'Votre panier est vide',
              message: 'Découvrez les boutiques et ajoutez vos coups de cœur.',
              action: ElevatedButton(
                onPressed: () => ShopShell.selectTab(context, ShopShell.homeTab),
                child: const Text('Découvrir les produits'),
              ),
            );
          }
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            children: [
              Text(plural(cart.itemCount, 'article'), style: const TextStyle(color: muted)),
              const SizedBox(height: 12),
              for (final slug in slugs) ...[
                Card(child: Padding(padding: const EdgeInsets.all(14), child: ShopCartSection(shopSlug: slug))),
                const SizedBox(height: 14),
              ],
              const Text(
                'Les commandes sont passées boutique par boutique.',
                textAlign: TextAlign.center,
                style: TextStyle(color: muted, fontSize: 12),
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Items of one shop with quantity steppers, subtotal and checkout button.
class ShopCartSection extends StatelessWidget {
  const ShopCartSection({required this.shopSlug, this.accentColor, this.showHeader = true, super.key});

  final String shopSlug;
  final Color? accentColor;
  final bool showHeader;

  @override
  Widget build(BuildContext context) {
    final cart = context.cart;
    return ListenableBuilder(
      listenable: cart,
      builder: (context, _) {
        final items = cart.itemsForShop(shopSlug);
        if (items.isEmpty) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Text('Votre panier est vide.', textAlign: TextAlign.center, style: TextStyle(color: muted)),
          );
        }
        final accent = accentColor ?? forest;
        final currency = items.first.currency;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (showHeader) ...[
              InkWell(
                onTap: () => openShop(context, shopSlug),
                child: Row(
                  children: [
                    Icon(Icons.storefront_outlined, color: accent, size: 20),
                    const SizedBox(width: 8),
                    Expanded(child: Text(items.first.shopName, style: headingStyle(fontSize: 17))),
                    const Icon(Icons.chevron_right, color: muted),
                  ],
                ),
              ),
              const Divider(height: 22),
            ],
            for (final item in items) _CartLine(item: item, accent: accent),
            const Divider(height: 14),
            InfoRow(label: 'Sous-total', value: formatPrice(cart.subtotalForShop(shopSlug), currency), bold: true, color: accent),
            const Text(
              'Livraison et remises calculées à l’étape suivante.',
              style: TextStyle(color: muted, fontSize: 12),
            ),
            const SizedBox(height: 12),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(backgroundColor: accent, foregroundColor: contrastingText(accent)),
              onPressed: () => pushScreen(context, CheckoutScreen(shopSlug: shopSlug)),
              icon: const Icon(Icons.lock_outline, size: 18),
              label: const Text('Passer la commande'),
            ),
          ],
        );
      },
    );
  }
}

class _CartLine extends StatelessWidget {
  const _CartLine({required this.item, required this.accent});

  final CartItem item;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final cart = context.cart;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          GestureDetector(
            onTap: item.productSlug.isEmpty
                ? null
                : () => pushScreen(context, ProductScreen(shopSlug: item.shopSlug, productSlug: item.productSlug)),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: ProductImage(url: item.imageUrl, color: accent, width: 64, height: 64),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.productName, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 3),
                Text(formatPrice(item.price, item.currency), style: TextStyle(color: accent, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Container(
            decoration: BoxDecoration(border: Border.all(color: border), borderRadius: BorderRadius.circular(30)),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  tooltip: item.quantity == 1 ? 'Retirer' : 'Diminuer la quantité',
                  visualDensity: VisualDensity.compact,
                  onPressed: () => cart.setQuantity(item.productId, item.quantity - 1),
                  icon: Icon(item.quantity == 1 ? Icons.delete_outline : Icons.remove, size: 18),
                ),
                Text('${item.quantity}', style: const TextStyle(fontWeight: FontWeight.w700)),
                IconButton(
                  tooltip: 'Augmenter la quantité',
                  visualDensity: VisualDensity.compact,
                  onPressed: item.quantity >= item.maxQuantity
                      ? null
                      : () => cart.setQuantity(item.productId, item.quantity + 1),
                  icon: const Icon(Icons.add, size: 18),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Bottom sheet cart of a single shop (the web storefront offcanvas).
Future<void> showShopCartSheet(BuildContext context, {required String shopSlug, Color? accentColor}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (context) => SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.8),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Votre panier', style: headingStyle(fontSize: 21)),
              const SizedBox(height: 12),
              ShopCartSection(shopSlug: shopSlug, accentColor: accentColor, showHeader: false),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Cart icon with this shop's item count, for storefront app bars.
class ShopCartButton extends StatelessWidget {
  const ShopCartButton({required this.shopSlug, this.accentColor, super.key});

  final String shopSlug;
  final Color? accentColor;

  @override
  Widget build(BuildContext context) {
    final cart = context.cart;
    return ListenableBuilder(
      listenable: cart,
      builder: (context, _) {
        final count = cart.countForShop(shopSlug);
        return IconButton(
          tooltip: 'Panier, ${plural(count, 'article')}',
          onPressed: () => showShopCartSheet(context, shopSlug: shopSlug, accentColor: accentColor),
          icon: Badge(
            isLabelVisible: count > 0,
            backgroundColor: danger,
            label: Text('$count'),
            child: const Icon(Icons.shopping_bag_outlined),
          ),
        );
      },
    );
  }
}

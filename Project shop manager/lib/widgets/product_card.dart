import 'package:flutter/material.dart';

import '../app/theme.dart';
import '../models/models.dart';
import 'common.dart';

class ProductCard extends StatelessWidget {
  const ProductCard({
    required this.product,
    required this.onTap,
    this.onAdd,
    this.accentColor,
    this.showShop = false,
    this.imageAspectRatio = 1,
    super.key,
  });

  final Product product;
  final VoidCallback onTap;
  final ValueChanged<Product>? onAdd;
  final Color? accentColor;
  final bool showShop;
  final double imageAspectRatio;

  @override
  Widget build(BuildContext context) {
    final accent = accentColor ?? Theme.of(context).colorScheme.primary;
    final textColor = Theme.of(context).textTheme.bodyMedium?.color ?? ink;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ProductImage(url: product.imageUrl, color: accent, aspectRatio: imageAspectRatio),
                Positioned(
                  top: 8,
                  left: 8,
                  right: 8,
                  child: Wrap(
                    spacing: 5,
                    runSpacing: 5,
                    children: [
                      if (product.isFeatured) _badge('À la une', Colors.white, forest),
                      if (product.hasDiscount)
                        _badge('-${(100 - product.price / product.compareAtPrice! * 100).round()} %', danger, Colors.white),
                      if (!product.inStock) _badge('Épuisé', ink.withValues(alpha: 0.75), Colors.white),
                    ],
                  ),
                ),
              ],
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(11, 9, 11, 11),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (showShop)
                      Text(
                        product.shopName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: goldInk, fontSize: 11, fontWeight: FontWeight.w600),
                      ),
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: textColor, fontWeight: FontWeight.w600, height: 1.25),
                    ),
                    const Spacer(),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: PriceTag(
                            amount: product.price,
                            currency: product.currency,
                            compareAt: product.compareAtPrice,
                            color: accent,
                            fontSize: 14,
                          ),
                        ),
                        if (onAdd != null)
                          SizedBox(
                            width: 36,
                            height: 36,
                            child: IconButton.filled(
                              tooltip: 'Ajouter au panier',
                              padding: EdgeInsets.zero,
                              onPressed: product.inStock ? () => onAdd!(product) : null,
                              style: IconButton.styleFrom(
                                backgroundColor: accent,
                                foregroundColor: contrastingText(accent),
                              ),
                              icon: const Icon(Icons.add_shopping_cart, size: 17),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _badge(String label, Color background, Color foreground) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(30)),
    child: Text(
      label.toUpperCase(),
      style: TextStyle(color: foreground, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 0.5),
    ),
  );
}

/// Horizontal product row used by the "list" layout and the "menu" format.
class ProductListTile extends StatelessWidget {
  const ProductListTile({
    required this.product,
    required this.onTap,
    this.onAdd,
    this.accentColor,
    this.showDescription = true,
    super.key,
  });

  final Product product;
  final VoidCallback onTap;
  final ValueChanged<Product>? onAdd;
  final Color? accentColor;
  final bool showDescription;

  @override
  Widget build(BuildContext context) {
    final accent = accentColor ?? Theme.of(context).colorScheme.primary;
    final textColor = Theme.of(context).textTheme.bodyMedium?.color ?? ink;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: ProductImage(url: product.imageUrl, color: accent, width: 84, height: 84),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: textColor, fontWeight: FontWeight.w700),
                    ),
                    if (showDescription && product.description != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        product.description!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: textColor.withValues(alpha: 0.62), fontSize: 12.5, height: 1.3),
                      ),
                    ],
                    const SizedBox(height: 6),
                    PriceTag(
                      amount: product.price,
                      currency: product.currency,
                      compareAt: product.compareAtPrice,
                      color: accent,
                      fontSize: 14,
                    ),
                  ],
                ),
              ),
              if (onAdd != null)
                IconButton.filledTonal(
                  tooltip: product.inStock ? 'Ajouter au panier' : 'Épuisé',
                  onPressed: product.inStock ? () => onAdd!(product) : null,
                  style: IconButton.styleFrom(
                    backgroundColor: accent.withValues(alpha: 0.12),
                    foregroundColor: accent,
                  ),
                  icon: const Icon(Icons.add),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

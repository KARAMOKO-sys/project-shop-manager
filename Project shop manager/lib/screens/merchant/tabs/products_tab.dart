import 'package:flutter/material.dart';

import '../../../app/format.dart';
import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';
import '../product_form_screen.dart';

/// Catalogue management, mirroring `components/dashboard/ShopProducts.vue`.
class ProductsTab extends StatefulWidget {
  const ProductsTab({required this.shop, super.key});

  final Shop shop;

  @override
  State<ProductsTab> createState() => _ProductsTabState();
}

class _ProductsTabState extends State<ProductsTab> with AutomaticKeepAliveClientMixin {
  final List<Product> _products = [];
  final Set<int> _expanded = {};
  final Set<int> _busy = {};
  int _page = 1;
  int _total = 0;
  bool _hasMore = false;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() {
      _error = null;
      if (_products.isEmpty) _loading = true;
    });
    try {
      final page = await context.api.getShopProducts(widget.shop.id);
      if (!mounted) return;
      setState(() {
        _products
          ..clear()
          ..addAll(page.items);
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on Object catch (error) {
      if (!mounted) return;
      if (_products.isEmpty) {
        setState(() => _error = errorText(error));
      } else {
        showToast(context, errorText(error), error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final page = await context.api.getShopProducts(widget.shop.id, page: _page + 1);
      if (!mounted) return;
      setState(() {
        final known = _products.map((product) => product.id).toSet();
        _products.addAll(page.items.where((product) => !known.contains(product.id)));
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error), error: true);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _replace(Product updated) {
    final index = _products.indexWhere((product) => product.id == updated.id);
    if (index >= 0) _products[index] = updated;
  }

  Future<void> _openForm([Product? product]) async {
    final saved = await Navigator.push<Product>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductFormScreen(shop: widget.shop, product: product),
      ),
    );
    if (!mounted || saved == null) return;
    if (product == null) {
      await _refresh();
    } else {
      setState(() => _replace(saved));
    }
  }

  Future<void> _remoderate(Product product) async {
    setState(() => _busy.add(product.id));
    try {
      final updated = await context.api.remoderateProduct(widget.shop.id, product.id);
      if (!mounted) return;
      setState(() => _replace(updated));
      showToast(context, '« ${updated.name} » a été revérifié par l’agent IA.');
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error, 'La vérification a échoué.'), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(product.id));
    }
  }

  Future<void> _delete(Product product) async {
    final confirmed = await confirmAction(
      context,
      title: 'Supprimer le produit ?',
      message: 'Supprimer définitivement « ${product.name} » ?',
    );
    if (!confirmed || !mounted) return;
    setState(() => _busy.add(product.id));
    try {
      await context.api.deleteProduct(widget.shop.id, product.id);
      if (!mounted) return;
      setState(() {
        _products.removeWhere((item) => item.id == product.id);
        _total = _total > 0 ? _total - 1 : 0;
      });
      showToast(context, 'Produit supprimé.');
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error, 'La suppression a échoué.'), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(product.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: _products.isEmpty
          ? null
          : FloatingActionButton.extended(
              heroTag: 'add-product-${widget.shop.id}',
              backgroundColor: forest,
              foregroundColor: ivory,
              onPressed: _openForm,
              icon: const Icon(Icons.add),
              label: const Text('Ajouter un produit'),
            ),
      body: _body(),
    );
  }

  Widget _body() {
    if (_loading && _products.isEmpty) return const LoadingView(text: 'Chargement des produits…');

    if (_products.isEmpty) {
      return RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (_error != null)
              MessageState(
                title: 'Impossible de charger les produits',
                message: _error,
                icon: Icons.inventory_2_outlined,
                onRetry: _refresh,
              )
            else
              MessageState(
                title: 'Aucun produit pour l’instant',
                message: 'Ajoutez votre premier produit : l’IA peut même rédiger la description pour vous.',
                icon: Icons.inventory_2_outlined,
                action: ElevatedButton.icon(
                  onPressed: _openForm,
                  icon: const Icon(Icons.auto_awesome, color: gold),
                  label: const Text('Ajouter mon premier produit'),
                ),
              ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
        itemCount: _products.length + 2,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Text.rich(
              TextSpan(
                children: [
                  const TextSpan(text: 'Produits '),
                  TextSpan(
                    text: '(${formatNumber(_total)})',
                    style: headingStyle(fontSize: 20, color: muted, fontWeight: FontWeight.w400),
                  ),
                ],
              ),
              style: headingStyle(fontSize: 20),
            );
          }
          if (index == _products.length + 1) {
            if (!_hasMore) return const SizedBox.shrink();
            return Center(
              child: OutlinedButton.icon(
                onPressed: _loadingMore ? null : _loadMore,
                icon: _loadingMore
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.expand_more),
                label: const Text('Charger plus'),
              ),
            );
          }
          final product = _products[index - 1];
          return _ProductCard(
            product: product,
            color: widget.shop.theme.primary,
            expanded: _expanded.contains(product.id),
            busy: _busy.contains(product.id),
            onToggle: () => setState(() {
              if (!_expanded.remove(product.id)) _expanded.add(product.id);
            }),
            onEdit: () => _openForm(product),
            onRemoderate: () => _remoderate(product),
            onDelete: () => _delete(product),
          );
        },
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
    required this.color,
    required this.expanded,
    required this.busy,
    required this.onToggle,
    required this.onEdit,
    required this.onRemoderate,
    required this.onDelete,
  });

  final Product product;
  final Color color;
  final bool expanded;
  final bool busy;
  final VoidCallback onToggle;
  final VoidCallback onEdit;
  final VoidCallback onRemoderate;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final outOfStock = product.stockQuantity <= 0;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            onTap: onEdit,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: ProductImage(url: product.imageUrl, color: color, width: 72, height: 72),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            if (product.isFeatured) ...[
                              const Icon(Icons.star_rounded, size: 16, color: gold),
                              const SizedBox(width: 3),
                            ],
                            Expanded(
                              child: Text(
                                product.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          product.category?.name ?? 'Sans catégorie',
                          style: const TextStyle(color: muted, fontSize: 12.5),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Expanded(
                              child: PriceTag(
                                amount: product.price,
                                currency: product.currency,
                                compareAt: product.compareAtPrice,
                                fontSize: 14,
                              ),
                            ),
                            Text(
                              outOfStock ? 'Épuisé' : 'Stock : ${formatNumber(product.stockQuantity)}',
                              style: TextStyle(
                                fontSize: 12.5,
                                color: outOfStock ? danger : muted,
                                fontWeight: outOfStock ? FontWeight.w700 : FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                StatusPill(
                  label: product.isPublished ? 'Publié' : 'Brouillon',
                  tone: product.isPublished ? Tone.primary : Tone.neutral,
                  icon: product.isPublished ? Icons.public : Icons.edit_outlined,
                ),
                InkWell(
                  borderRadius: BorderRadius.circular(30),
                  onTap: onToggle,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      ModerationBadge(product.moderation),
                      Icon(
                        expanded ? Icons.expand_less : Icons.expand_more,
                        size: 18,
                        color: muted,
                        semanticLabel: expanded
                            ? 'Masquer le détail de la vérification'
                            : 'Afficher le détail de la vérification',
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          AnimatedSize(
            duration: const Duration(milliseconds: 200),
            alignment: Alignment.topCenter,
            child: expanded
                ? Container(
                    margin: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: tertiary, borderRadius: BorderRadius.circular(12)),
                    child: ModerationDetails(moderation: product.moderation),
                  )
                : const SizedBox(width: double.infinity),
          ),
          const SizedBox(height: 4),
          const Divider(),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Row(
              children: [
                Expanded(
                  child: TextButton.icon(
                    onPressed: busy ? null : onEdit,
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text('Modifier'),
                  ),
                ),
                Expanded(
                  child: TextButton.icon(
                    onPressed: busy ? null : onRemoderate,
                    icon: busy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.refresh, size: 18),
                    label: const Text('Revérifier'),
                  ),
                ),
                Expanded(
                  child: TextButton.icon(
                    onPressed: busy ? null : onDelete,
                    style: TextButton.styleFrom(foregroundColor: danger),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Supprimer'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// AI moderation detail, mirroring `components/dashboard/ModerationDetails.vue`.
class ModerationDetails extends StatelessWidget {
  const ModerationDetails({required this.moderation, super.key});

  final Moderation moderation;

  @override
  Widget build(BuildContext context) {
    final score = moderation.riskScore.clamp(0, 100);
    final riskColor = score >= 70
        ? danger
        : score >= 30
        ? warning
        : success;
    final engine = moderation.engine == 'claude' ? 'l’agent IA avancé' : 'l’agent IA local';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 10,
          runSpacing: 6,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            ModerationBadge(moderation),
            Text(
              moderation.checkedAt == null
                  ? 'Vérifié par $engine'
                  : 'Vérifié par $engine · ${formatDate(moderation.checkedAt)}',
              style: const TextStyle(color: muted, fontSize: 12.5),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            const Expanded(
              child: Text('Score de risque', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            ),
            Text('$score/100', style: const TextStyle(fontSize: 13)),
          ],
        ),
        const SizedBox(height: 5),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: score / 100,
            minHeight: 8,
            color: riskColor,
            backgroundColor: Colors.white,
          ),
        ),
        const SizedBox(height: 12),
        if (moderation.reasons.isNotEmpty) ...[
          const Text('Motifs relevés :', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
          const SizedBox(height: 4),
          for (final reason in moderation.reasons)
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    '•  ',
                    style: TextStyle(color: goldInk, fontWeight: FontWeight.w700),
                  ),
                  Expanded(child: Text(reason, style: const TextStyle(fontSize: 13, height: 1.35))),
                ],
              ),
            ),
        ] else
          const Text('Aucun problème détecté.', style: TextStyle(color: muted, fontSize: 13)),
        if (moderation.note != null) ...[
          const SizedBox(height: 10),
          Text.rich(
            TextSpan(
              children: [
                const TextSpan(
                  text: 'Note du modérateur : ',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                TextSpan(text: moderation.note),
              ],
            ),
            style: const TextStyle(fontSize: 13, height: 1.35),
          ),
        ],
      ],
    );
  }
}

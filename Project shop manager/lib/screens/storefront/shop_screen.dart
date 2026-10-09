import 'package:flutter/material.dart';

import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../../widgets/product_card.dart';
import '../chat_screen.dart';
import 'cart_screen.dart';
import 'order_tracking_screen.dart';

/// Public storefront of a shop (`/boutique/{slug}`), rendered with the merchant's theme
/// and page format.
class ShopScreen extends StatefulWidget {
  const ShopScreen({required this.slug, super.key});

  final String slug;

  @override
  State<ShopScreen> createState() => _ShopScreenState();
}

class _ShopScreenState extends State<ShopScreen> {
  final _search = TextEditingController();
  late Future<Shop> _shop = context.api.getStorefront(widget.slug);

  final List<Product> _products = [];
  String _category = '';
  String _query = '';
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  bool _loading = true;
  bool _loadingMore = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadProducts({bool more = false}) async {
    final page = more ? _page + 1 : 1;
    setState(() {
      if (more) {
        _loadingMore = true;
      } else {
        _loading = true;
        _error = null;
      }
    });
    try {
      final result = await context.api.getStorefrontProducts(
        widget.slug,
        category: _category,
        search: _query,
        page: page,
      );
      if (!mounted) return;
      setState(() {
        if (!more) _products.clear();
        _products.addAll(result.items);
        _page = result.currentPage;
        _lastPage = result.lastPage;
        _total = result.total;
      });
    } on Object catch (error) {
      if (!mounted) return;
      if (more) {
        showToast(context, errorText(error), error: true);
      } else {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  Future<void> _refresh() async {
    final shop = context.api.getStorefront(widget.slug);
    setState(() => _shop = shop);
    await Future.wait([shop.then((_) {}, onError: (_) {}), _loadProducts()]);
  }

  void _selectCategory(String slug) {
    if (slug == _category) return;
    setState(() => _category = slug);
    _loadProducts();
  }

  void _applySearch(String value) {
    FocusScope.of(context).unfocus();
    setState(() => _query = value.trim());
    _loadProducts();
  }

  Future<void> _add(Shop shop, Product product) async {
    await context.cart.add(product, shop: shop);
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('« ${product.name} » ajouté au panier.'),
          action: SnackBarAction(
            label: 'Voir',
            textColor: gold,
            onPressed: () => showShopCartSheet(context, shopSlug: shop.slug, accentColor: shop.theme.primary),
          ),
        ),
      );
  }

  void _openChat(Shop shop) {
    final api = context.api;
    pushScreen(
      context,
      ChatScreen(
        title: 'Assistant ${shop.name}',
        subtitle: 'Questions, conseils, suivi de commande',
        welcome: 'Bienvenue chez ${shop.name} ! Je peux vous aider à trouver un produit ou répondre à vos questions.',
        suggestions: const ['Quels sont vos délais de livraison ?', 'Comment puis-je payer ?', 'Où en est ma commande ?'],
        accentColor: shop.theme.primary,
        send: (message, history) => api.shopChat(shop.slug, message, history),
        onProductTap: (product) => openProduct(context, product, shopSlug: shop.slug),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Shop>(
      future: _shop,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          final notFound = snapshot.error is ApiException && (snapshot.error! as ApiException).isNotFound;
          return Scaffold(
            appBar: AppBar(),
            body: MessageState(
              icon: Icons.storefront_outlined,
              title: notFound ? 'Boutique introuvable' : 'Boutique indisponible',
              message: notFound
                  ? 'Cette boutique n’existe pas ou est momentanément indisponible.'
                  : errorText(snapshot.error!),
              onRetry: notFound ? null : _refresh,
            ),
          );
        }
        final shop = snapshot.data;
        if (shop == null) return const Scaffold(body: LoadingView(text: 'Ouverture de la boutique…'));
        return Theme(data: buildShopTheme(context, shop.theme), child: _buildShop(shop));
      },
    );
  }

  Widget _buildShop(Shop shop) {
    final theme = shop.theme;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            ShopLogo(name: shop.name, logoUrl: shop.logoUrl, color: theme.primary, size: 34),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                shop.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: theme.text),
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Suivre ma commande',
            onPressed: () => pushScreen(context, OrderTrackingScreen(shop: shop)),
            icon: const Icon(Icons.local_shipping_outlined),
          ),
          ShopCartButton(shopSlug: shop.slug, accentColor: theme.primary),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        heroTag: 'shop-chat',
        tooltip: 'Assistant ${shop.name}',
        backgroundColor: theme.primary,
        foregroundColor: contrastingText(theme.primary),
        onPressed: () => _openChat(shop),
        child: const Icon(Icons.chat_bubble_outline),
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _hero(shop)),
            SliverToBoxAdapter(child: _filters(shop)),
            ..._catalog(shop),
            SliverToBoxAdapter(child: _footer(shop)),
          ],
        ),
      ),
    );
  }

  // ---------- Hero ----------

  Widget _hero(Shop shop) {
    final theme = shop.theme;
    final format = theme.format;
    final minimal = theme.heroStyle == 'minimal';
    final onHero = minimal ? theme.text : contrastingText(theme.primary);
    final heroImage = theme.heroStyle == 'image'
        ? _products.where((product) => product.imageUrl != null).firstOrNull?.imageUrl
        : null;
    final editorial = format == ShopFormat.editorial || format == ShopFormat.showcase;
    final compact = format == ShopFormat.catalog || format == ShopFormat.menu;

    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (editorial)
          Text(
            shop.name.toUpperCase(),
            style: TextStyle(color: onHero.withValues(alpha: 0.75), letterSpacing: 2, fontSize: 11, fontWeight: FontWeight.w700),
          ),
        if (editorial) const SizedBox(height: 10),
        Text(
          editorial ? (shop.tagline ?? shop.name) : shop.name,
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            color: onHero,
            fontWeight: FontWeight.w800,
            fontSize: compact ? 24 : (editorial ? 30 : 28),
            height: 1.12,
          ),
        ),
        if (!editorial && shop.tagline != null) ...[
          const SizedBox(height: 8),
          Text(shop.tagline!, style: TextStyle(color: onHero.withValues(alpha: 0.88), fontSize: 15.5, height: 1.35)),
        ],
        if (!compact && shop.description != null) ...[
          const SizedBox(height: 8),
          Text(
            shop.description!,
            maxLines: 4,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: onHero.withValues(alpha: 0.75), height: 1.45),
          ),
        ],
      ],
    );

    final decoration = switch (theme.heroStyle) {
      'minimal' => BoxDecoration(
        color: theme.surface,
        borderRadius: BorderRadius.circular(theme.radius + 6),
        border: Border.all(color: theme.text.withValues(alpha: 0.08)),
      ),
      _ => BoxDecoration(
        color: theme.primary,
        borderRadius: BorderRadius.circular(theme.radius + 6),
        gradient: LinearGradient(
          colors: [theme.primary, Color.lerp(theme.primary, theme.secondary, 0.45)!],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
    };

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 6, 16, 12),
      clipBehavior: Clip.antiAlias,
      decoration: decoration,
      child: Stack(
        children: [
          if (heroImage != null)
            Positioned.fill(
              child: Image.network(heroImage, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox()),
            ),
          if (heroImage != null)
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [theme.primary.withValues(alpha: 0.9), theme.primary.withValues(alpha: 0.45)],
                    begin: Alignment.bottomLeft,
                    end: Alignment.topRight,
                  ),
                ),
              ),
            ),
          Padding(padding: EdgeInsets.all(compact ? 18 : 22), child: content),
        ],
      ),
    );
  }

  // ---------- Filters ----------

  Widget _filters(Shop shop) {
    final theme = shop.theme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
          child: TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: _applySearch,
            decoration: InputDecoration(
              hintText: 'Rechercher un produit…',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _query.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Effacer la recherche',
                      onPressed: () {
                        _search.clear();
                        _applySearch('');
                      },
                      icon: const Icon(Icons.close),
                    ),
            ),
          ),
        ),
        if (shop.categories.isNotEmpty)
          SizedBox(
            height: 44,
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              scrollDirection: Axis.horizontal,
              children: [
                _categoryChip(theme, '', 'Tout'),
                for (final category in shop.categories) _categoryChip(theme, category.slug, category.name),
              ],
            ),
          ),
        if (theme.format == ShopFormat.catalog && !_loading)
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
            child: Text(
              '$_total produit${_total > 1 ? 's' : ''}',
              style: TextStyle(color: theme.text.withValues(alpha: 0.6), fontSize: 12.5),
            ),
          ),
      ],
    );
  }

  Widget _categoryChip(ShopTheme theme, String slug, String label) {
    final selected = _category == slug;
    final onPrimary = contrastingText(theme.primary);
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        selectedColor: theme.primary,
        backgroundColor: theme.surface,
        labelStyle: TextStyle(color: selected ? onPrimary : theme.text, fontWeight: FontWeight.w600),
        side: BorderSide(color: selected ? theme.primary : theme.text.withValues(alpha: 0.12)),
        onSelected: (_) => _selectCategory(slug),
      ),
    );
  }

  // ---------- Catalog ----------

  List<Widget> _catalog(Shop shop) {
    if (_loading) {
      return const [SliverToBoxAdapter(child: SizedBox(height: 220, child: LoadingView(text: 'Chargement des produits…')))];
    }
    if (_error != null) {
      return [
        SliverToBoxAdapter(
          child: MessageState(
            icon: Icons.cloud_off,
            title: 'Impossible de charger les produits',
            message: errorText(_error!, 'Vérifiez votre connexion et réessayez.'),
            onRetry: _loadProducts,
          ),
        ),
      ];
    }
    if (_products.isEmpty) {
      final filtered = _query.isNotEmpty || _category.isNotEmpty;
      return [
        SliverToBoxAdapter(
          child: MessageState(
            icon: Icons.search,
            title: filtered ? 'Aucun produit ne correspond' : 'La boutique se prépare',
            message: filtered ? 'Essayez une autre recherche ou catégorie.' : 'Les premiers produits arrivent très bientôt.',
          ),
        ),
      ];
    }

    final format = shop.theme.format;
    final slivers = switch (format) {
      ShopFormat.editorial => _editorial(shop),
      ShopFormat.menu => _menu(shop),
      ShopFormat.showcase => _showcase(shop),
      ShopFormat.singleProduct => _singleProduct(shop),
      ShopFormat.catalog => [_grid(shop, _products, columns: 3, aspectRatio: 0.5)],
      ShopFormat.classic => [_layout(shop, _products)],
    };
    return [
      const SliverToBoxAdapter(child: SizedBox(height: 14)),
      ...slivers,
      if (_page < _lastPage)
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Center(
              child: OutlinedButton.icon(
                onPressed: _loadingMore ? null : () => _loadProducts(more: true),
                icon: _loadingMore
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.expand_more),
                label: const Text('Voir plus de produits'),
              ),
            ),
          ),
        ),
    ];
  }

  /// Grid, list or masonry according to the theme's `layout`.
  Widget _layout(Shop shop, List<Product> products) => switch (shop.theme.layout) {
    'list' => _list(shop, products),
    'masonry' => _masonry(shop, products),
    _ => _grid(shop, products),
  };

  Widget _grid(Shop shop, List<Product> products, {int columns = 2, double aspectRatio = 0.6}) {
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: SliverGrid(
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: columns,
          mainAxisSpacing: 12,
          crossAxisSpacing: columns > 2 ? 8 : 12,
          childAspectRatio: aspectRatio,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) => _card(shop, products[index], compact: columns > 2),
          childCount: products.length,
        ),
      ),
    );
  }

  Widget _card(Shop shop, Product product, {bool compact = false, double imageAspectRatio = 1}) => ProductCard(
    product: product,
    accentColor: shop.theme.primary,
    imageAspectRatio: imageAspectRatio,
    onTap: () => openProduct(context, product, shopSlug: shop.slug),
    onAdd: compact ? null : (product) => _add(shop, product),
  );

  Widget _list(Shop shop, List<Product> products, {bool showDescription = true}) {
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: SliverList.separated(
        itemCount: products.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) => ProductListTile(
          product: products[index],
          accentColor: shop.theme.primary,
          showDescription: showDescription,
          onTap: () => openProduct(context, products[index], shopSlug: shop.slug),
          onAdd: (product) => _add(shop, product),
        ),
      ),
    );
  }

  Widget _masonry(Shop shop, List<Product> products) {
    Widget column(int parity) => Expanded(
      child: Column(
        children: [
          for (var index = parity; index < products.length; index += 2)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AspectRatio(
                aspectRatio: (index ~/ 2 + parity).isEven ? 0.52 : 0.66,
                child: _card(shop, products[index], imageAspectRatio: (index ~/ 2 + parity).isEven ? 0.8 : 1.2),
              ),
            ),
        ],
      ),
    );
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: SliverToBoxAdapter(
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [column(0), const SizedBox(width: 12), column(1)]),
      ),
    );
  }

  /// Magazine layout: the first products are told as numbered stories.
  List<Widget> _editorial(Shop shop) {
    final stories = _products.take(3).toList();
    final others = _products.skip(3).toList();
    final theme = shop.theme;
    return [
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverList.separated(
          itemCount: stories.length,
          separatorBuilder: (_, _) => const SizedBox(height: 26),
          itemBuilder: (context, index) {
            final product = stories[index];
            return InkWell(
              onTap: () => openProduct(context, product, shopSlug: shop.slug),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(theme.radius + 4),
                    child: ProductImage(url: product.imageUrl, color: theme.primary, aspectRatio: 4 / 3),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'N° ${(index + 1).toString().padLeft(2, '0')}${product.category == null ? '' : ' · ${product.category!.name}'}',
                    style: TextStyle(color: theme.secondary, fontWeight: FontWeight.w700, letterSpacing: 1.2, fontSize: 12),
                  ),
                  const SizedBox(height: 4),
                  Text(product.name, style: TextStyle(color: theme.text, fontSize: 24, fontWeight: FontWeight.w700, height: 1.15)),
                  if (product.description != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      product.description!,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: theme.text.withValues(alpha: 0.65), height: 1.45),
                    ),
                  ],
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: PriceTag(amount: product.price, currency: product.currency, compareAt: product.compareAtPrice, fontSize: 17),
                      ),
                      if (product.inStock)
                        ElevatedButton.icon(
                          onPressed: () => _add(shop, product),
                          style: ElevatedButton.styleFrom(minimumSize: const Size(40, 42)),
                          icon: const Icon(Icons.add_shopping_cart, size: 18),
                          label: const Text('Ajouter'),
                        )
                      else
                        const StatusPill(label: 'Épuisé'),
                    ],
                  ),
                ],
              ),
            );
          },
        ),
      ),
      if (others.isNotEmpty) ...[
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 30, 16, 14),
          sliver: SliverToBoxAdapter(
            child: Text('Toute la collection', style: TextStyle(color: theme.text, fontSize: 22, fontWeight: FontWeight.w700)),
          ),
        ),
        _grid(shop, others),
      ],
    ];
  }

  /// Restaurant menu: products grouped by category.
  List<Widget> _menu(Shop shop) {
    final groups = <String, List<Product>>{};
    for (final product in _products) {
      groups.putIfAbsent(product.category?.name ?? 'À la carte', () => []).add(product);
    }
    final theme = shop.theme;
    return [
      for (final entry in groups.entries) ...[
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 10),
          sliver: SliverToBoxAdapter(
            child: Row(
              children: [
                Text(entry.key, style: TextStyle(color: theme.text, fontSize: 20, fontWeight: FontWeight.w800)),
                const SizedBox(width: 12),
                Expanded(child: Container(height: 1.5, color: theme.secondary.withValues(alpha: 0.5))),
              ],
            ),
          ),
        ),
        _list(shop, entry.value),
        const SliverToBoxAdapter(child: SizedBox(height: 12)),
      ],
    ];
  }

  /// Luxury showcase: one large visual per product.
  List<Widget> _showcase(Shop shop) {
    return [
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverList.separated(
          itemCount: _products.length,
          separatorBuilder: (_, _) => const SizedBox(height: 18),
          itemBuilder: (context, index) => SizedBox(
            height: MediaQuery.sizeOf(context).width * 1.12 + 92,
            child: _card(shop, _products[index], imageAspectRatio: 0.9),
          ),
        ),
      ),
    ];
  }

  /// Sales page centered on the flagship product, other products below.
  List<Widget> _singleProduct(Shop shop) {
    final hero = _products.firstWhere((product) => product.isFeatured, orElse: () => _products.first);
    final others = _products.where((product) => product.id != hero.id).toList();
    final theme = shop.theme;
    return [
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverToBoxAdapter(
          child: Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ProductImage(url: hero.imageUrl, color: theme.primary, aspectRatio: 1),
                Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(hero.name, style: TextStyle(color: theme.text, fontSize: 25, fontWeight: FontWeight.w800, height: 1.15)),
                      const SizedBox(height: 8),
                      PriceTag(amount: hero.price, currency: hero.currency, compareAt: hero.compareAtPrice, fontSize: 21),
                      if (hero.description != null) ...[
                        const SizedBox(height: 10),
                        Text(
                          hero.description!,
                          maxLines: 6,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: theme.text.withValues(alpha: 0.7), height: 1.5),
                        ),
                      ],
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed: hero.inStock ? () => _add(shop, hero) : null,
                              icon: const Icon(Icons.shopping_bag_outlined),
                              label: Text(hero.inStock ? 'Ajouter au panier' : 'Épuisé'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          OutlinedButton(
                            onPressed: () => openProduct(context, hero, shopSlug: shop.slug),
                            child: const Text('Détails'),
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
      ),
      if (others.isNotEmpty) ...[
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 26, 16, 12),
          sliver: SliverToBoxAdapter(
            child: Text('Complétez votre commande', style: TextStyle(color: theme.text, fontSize: 20, fontWeight: FontWeight.w700)),
          ),
        ),
        _grid(shop, others),
      ],
    ];
  }

  Widget _footer(Shop shop) {
    final textColor = shop.theme.text.withValues(alpha: 0.6);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 30, 16, 96),
      child: Column(
        children: [
          TextButton.icon(
            onPressed: () => pushScreen(context, OrderTrackingScreen(shop: shop)),
            icon: const Icon(Icons.local_shipping_outlined, size: 18),
            label: const Text('Suivre ma commande'),
          ),
          Text('© ${shop.name} · Propulsé par ShopAI', style: TextStyle(color: textColor, fontSize: 12)),
        ],
      ),
    );
  }
}

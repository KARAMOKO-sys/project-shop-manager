import 'package:flutter/material.dart';

import '../../app/format.dart';
import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../main.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../../widgets/product_card.dart';
import '../../widgets/theme_preview.dart';
import '../chat_screen.dart';
import '../merchant/create_shop_screen.dart';

/// Platform landing page, mirroring `pages/index.vue`.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<HomeData> _home = context.api.getHome();

  static const _trust = [
    (Icons.phone_android, 'Mobile Money'),
    (Icons.verified_user_outlined, 'Agent anti-fraude IA'),
    (Icons.forum_outlined, 'Chatbot 24/7'),
  ];

  static const _steps = [
    ('Décrivez', '« Je vends des pagnes wax et des sacs faits main à Abidjan. » Une phrase suffit.'),
    ('Personnalisez', 'Choisissez un modèle, ajustez vos couleurs. L’IA a déjà tout préparé.'),
    ('Vendez', 'Partagez votre lien sur WhatsApp, Facebook et TikTok. C’est en ligne !'),
  ];

  static const _features = [
    (Icons.auto_fix_high, 'Assistant IA', 'Décrivez votre activité en une phrase : l’IA propose le nom, les catégories, les textes et le design de votre boutique.'),
    (Icons.forum_outlined, 'Chatbot 24h/24', 'Un vendeur virtuel répond à vos clients jour et nuit, recommande vos produits et rassure avant l’achat.'),
    (Icons.verified_user_outlined, 'Agent anti-fraude', 'Chaque produit est vérifié par notre agent IA avant publication : vos clients achètent en confiance.'),
    (Icons.phone_android, 'Mobile Money', 'Orange Money, MTN MoMo, Wave, Moov… et paiement à la livraison. Encaissez comme vos clients paient.'),
  ];

  Future<void> _refresh() async {
    final future = context.api.getHome();
    setState(() => _home = future);
    try {
      await future;
    } on Object {
      // The error state is rendered by the FutureBuilder.
    }
  }

  void _openChat() {
    final api = context.api;
    pushScreen(
      context,
      ChatScreen(
        title: 'Assistant ShopAI',
        welcome: 'Bonjour ! Je suis l’assistant ShopAI. Posez-moi vos questions sur la création de votre boutique en ligne.',
        suggestions: const ['Comment créer ma boutique ?', 'Quels moyens de paiement ?', 'Combien ça coûte ?'],
        send: api.platformChat,
      ),
    );
  }

  void _createShop() => pushScreen(context, const CreateShopScreen());

  Future<void> _add(Product product) async {
    await context.cart.add(product);
    if (mounted) showToast(context, '« ${product.name} » ajouté au panier.');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(
        heroTag: 'platform-chat',
        tooltip: 'Assistant ShopAI',
        backgroundColor: forest,
        foregroundColor: gold,
        onPressed: _openChat,
        child: const Icon(Icons.chat_bubble_outline),
      ),
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<HomeData>(
            future: _home,
            builder: (context, snapshot) {
              final home = snapshot.data;
              return CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 12, 12, 8),
                      child: Row(
                        children: [
                          const BrandMark(compact: true),
                          const Spacer(),
                          TextButton.icon(
                            onPressed: () => ShopShell.selectTab(context, ShopShell.accountTab),
                            icon: const Icon(Icons.storefront_outlined, size: 18),
                            label: const Text('Espace vendeur'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(child: _hero(home)),
                  ..._featured(snapshot),
                  if (home != null && home.templates.isNotEmpty) ..._templates(home),
                  SliverToBoxAdapter(child: _howItWorks()),
                  if (home != null) ..._shops(home),
                  SliverToBoxAdapter(child: _featuresStrip()),
                  SliverToBoxAdapter(child: _cta()),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _hero(HomeData? home) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 6, 16, 8),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: const LinearGradient(
          colors: [forest, Color(0xFF174C39)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Stack(
        children: [
          const Positioned.fill(child: CustomPaint(painter: MotifPainter(opacity: 0.12))),
          Padding(
            padding: const EdgeInsets.fromLTRB(22, 26, 22, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'LE E-COMMERCE AFRICAIN, EN TOUTE ÉLÉGANCE',
                  style: TextStyle(color: gold, fontSize: 10.5, fontWeight: FontWeight.w700, letterSpacing: 1.3),
                ),
                const SizedBox(height: 12),
                Text.rich(
                  TextSpan(
                    style: headingStyle(fontSize: 30, color: ivory, height: 1.12),
                    children: [
                      const TextSpan(text: 'Votre boutique en ligne, '),
                      TextSpan(
                        text: 'prête en 5 minutes.',
                        style: headingStyle(fontSize: 30, color: gold, height: 1.12).copyWith(fontStyle: FontStyle.italic),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Décrivez votre activité en une phrase. ShopAI crée votre boutique, rédige vos fiches produits, '
                  'vérifie chaque article et répond à vos clients jour et nuit.',
                  style: TextStyle(color: ivory.withValues(alpha: 0.82), height: 1.45),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton(
                        onPressed: _createShop,
                        style: ElevatedButton.styleFrom(backgroundColor: gold, foregroundColor: ink),
                        child: const Text('Créer ma boutique'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => ShopShell.selectTab(context, ShopShell.templatesTab),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: ivory,
                          minimumSize: const Size(44, 50),
                          side: BorderSide(color: gold.withValues(alpha: 0.7)),
                        ),
                        child: const Text('Voir les modèles'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 14,
                  runSpacing: 8,
                  children: [
                    for (final (icon, label) in _trust)
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(icon, size: 15, color: gold),
                          const SizedBox(width: 5),
                          Text(label, style: TextStyle(color: ivory.withValues(alpha: 0.85), fontSize: 12)),
                        ],
                      ),
                  ],
                ),
                if (home != null) ...[
                  const SizedBox(height: 18),
                  Container(height: 1, color: ivory.withValues(alpha: 0.12)),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      _stat(formatNumber(home.shopCount), 'Boutiques actives'),
                      const SizedBox(width: 28),
                      _stat(formatNumber(home.productCount), 'Produits vérifiés'),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _stat(String value, String label) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(value, style: headingStyle(fontSize: 24, color: gold, fontWeight: FontWeight.w700)),
      Text(label, style: TextStyle(color: ivory.withValues(alpha: 0.7), fontSize: 12)),
    ],
  );

  Widget _sectionPadding(Widget child, {double top = 30}) =>
      Padding(padding: EdgeInsets.fromLTRB(20, top, 20, 14), child: child);

  List<Widget> _featured(AsyncSnapshot<HomeData> snapshot) {
    final heading = SliverToBoxAdapter(
      child: _sectionPadding(
        const SectionHeading(
          eyebrow: 'Sélection',
          title: 'Produits à la une',
          subtitle: 'Tous vérifiés par notre agent IA',
        ),
      ),
    );
    if (snapshot.hasError) {
      return [
        heading,
        SliverToBoxAdapter(
          child: MessageState(
            icon: Icons.cloud_off,
            title: 'Impossible de charger les produits',
            message: errorText(snapshot.error!, 'Le service est momentanément indisponible.'),
            onRetry: _refresh,
          ),
        ),
      ];
    }
    final home = snapshot.data;
    if (home == null) {
      return [heading, const SliverToBoxAdapter(child: SizedBox(height: 160, child: LoadingView(text: 'Chargement des produits…')))];
    }
    if (home.featuredProducts.isEmpty) {
      return [
        heading,
        const SliverToBoxAdapter(
          child: MessageState(
            icon: Icons.shopping_bag_outlined,
            title: 'Bientôt des pépites ici',
            message: 'Les premiers produits des boutiques ShopAI apparaîtront très vite.',
          ),
        ),
      ];
    }
    return [
      heading,
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverGrid(
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 0.6,
          ),
          delegate: SliverChildBuilderDelegate(
            (context, index) {
              final product = home.featuredProducts[index];
              return ProductCard(
                product: product,
                showShop: true,
                onTap: () => openProduct(context, product),
                onAdd: _add,
              );
            },
            childCount: home.featuredProducts.length,
          ),
        ),
      ),
    ];
  }

  List<Widget> _templates(HomeData home) {
    final templates = home.templates.take(6).toList();
    return [
      SliverToBoxAdapter(
        child: _sectionPadding(
          SectionHeading(
            eyebrow: 'Modèles de boutique',
            title: 'Un format pour chaque activité',
            subtitle: 'Boutique classique, magazine, catalogue, carte de restaurant, vitrine de luxe ou page produit unique.',
            trailing: TextButton(
              onPressed: () => ShopShell.selectTab(context, ShopShell.templatesTab),
              child: const Text('Tout voir'),
            ),
          ),
          top: 36,
        ),
      ),
      SliverToBoxAdapter(
        child: SizedBox(
          height: 330,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            scrollDirection: Axis.horizontal,
            itemCount: templates.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, index) => SizedBox(
              width: 250,
              child: TemplateCard(
                template: templates[index],
                onTap: () => pushScreen(context, CreateShopScreen(templateKey: templates[index].key)),
              ),
            ),
          ),
        ),
      ),
    ];
  }

  Widget _howItWorks() {
    return _sectionPadding(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SectionHeading(eyebrow: 'Comment ça marche', title: 'Trois étapes, cinq minutes'),
          const SizedBox(height: 16),
          for (final (index, (title, text)) in _steps.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('0${index + 1}', style: headingStyle(fontSize: 30, color: gold, fontWeight: FontWeight.w700)),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(title, style: headingStyle(fontSize: 18)),
                            const SizedBox(height: 4),
                            Text(text, style: const TextStyle(color: muted, height: 1.4)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
      top: 36,
    );
  }

  List<Widget> _shops(HomeData home) {
    return [
      SliverToBoxAdapter(
        child: _sectionPadding(const SectionHeading(eyebrow: 'Communauté', title: 'Boutiques populaires'), top: 22),
      ),
      if (home.shops.isEmpty)
        SliverToBoxAdapter(
          child: MessageState(
            icon: Icons.storefront_outlined,
            title: 'Soyez la première boutique !',
            message: 'Créez votre boutique et apparaissez ici.',
            action: ElevatedButton(onPressed: _createShop, child: const Text('Créer ma boutique')),
          ),
        )
      else
        SliverToBoxAdapter(
          child: SizedBox(
            height: 186,
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              scrollDirection: Axis.horizontal,
              itemCount: home.shops.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) => _shopCard(home.shops[index]),
            ),
          ),
        ),
    ];
  }

  Widget _shopCard(Shop shop) {
    return SizedBox(
      width: 220,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => openShop(context, shop.slug),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 8,
                decoration: BoxDecoration(gradient: LinearGradient(colors: [shop.theme.primary, shop.theme.secondary])),
              ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        ShopLogo(name: shop.name, logoUrl: shop.logoUrl, color: shop.theme.primary, size: 40),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(shop.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: headingStyle(fontSize: 16)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      shop.tagline ?? shop.description ?? '',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: muted, fontSize: 12.5, height: 1.35),
                    ),
                    const SizedBox(height: 8),
                    if (shop.productCount != null)
                      Row(
                        children: [
                          const Icon(Icons.inventory_2_outlined, size: 14, color: goldInk),
                          const SizedBox(width: 4),
                          Text(
                            plural(shop.productCount!, 'produit'),
                            style: const TextStyle(color: goldInk, fontSize: 12, fontWeight: FontWeight.w600),
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
    );
  }

  Widget _featuresStrip() {
    return Container(
      margin: const EdgeInsets.only(top: 30),
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(color: forest),
      child: Stack(
        children: [
          const Positioned.fill(child: CustomPaint(painter: MotifPainter(opacity: 0.08))),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 30, 20, 26),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeading(eyebrow: 'Vos alliés au quotidien', title: 'L’IA travaille pour vous', color: ivory),
                const SizedBox(height: 18),
                for (final (icon, title, text) in _features)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(color: gold.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(12)),
                          child: Icon(icon, color: gold, size: 21),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(title, style: const TextStyle(color: ivory, fontWeight: FontWeight.w700, fontSize: 15)),
                              const SizedBox(height: 3),
                              Text(text, style: TextStyle(color: ivory.withValues(alpha: 0.75), fontSize: 13, height: 1.4)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _cta() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 26, 16, 96),
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: goldSoft,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: gold.withValues(alpha: 0.35)),
        ),
        child: Column(
          children: [
            Text('Prêt à vendre en ligne ?', textAlign: TextAlign.center, style: headingStyle(fontSize: 24)),
            const SizedBox(height: 8),
            const Text(
              'Votre boutique est prête en moins de temps qu’il n’en faut pour préparer un attiéké.',
              textAlign: TextAlign.center,
              style: TextStyle(color: muted, height: 1.4),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(onPressed: _createShop, child: const Text('Créer ma boutique gratuitement')),
            ),
          ],
        ),
      ),
    );
  }
}

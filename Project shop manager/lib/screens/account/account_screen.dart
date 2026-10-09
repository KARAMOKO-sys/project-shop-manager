import 'package:flutter/material.dart';

import '../../app/format.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../state/session.dart';
import '../../widgets/common.dart';
import '../admin/moderation_screen.dart';
import '../auth/login_screen.dart';
import '../auth/register_screen.dart';
import '../merchant/create_shop_screen.dart';
import '../merchant/shop_dashboard_screen.dart';

/// Root of the "Mon espace" tab: merchant pitch when signed out, the shop list
/// (web `pages/dashboard/index.vue`) when signed in.
class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  Session? _session;
  bool? _wasAuthenticated;
  List<Shop>? _shops;
  Object? _error;
  bool _loading = false;
  int _request = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final session = context.session;
    if (identical(session, _session)) return;
    _session?.removeListener(_onSessionChanged);
    _session = session..addListener(_onSessionChanged);
    // Already rebuilding: update the fields without setState.
    _syncWithSession(rebuild: false);
  }

  @override
  void dispose() {
    _session?.removeListener(_onSessionChanged);
    super.dispose();
  }

  void _onSessionChanged() => _syncWithSession();

  /// Loads the shops on sign-in and forgets them on sign-out.
  void _syncWithSession({bool rebuild = true}) {
    final authenticated = _session!.isAuthenticated;
    if (authenticated == _wasAuthenticated) return;
    _wasAuthenticated = authenticated;
    if (authenticated) {
      _loadShops(rebuild: rebuild);
      return;
    }
    _request++;
    void reset() {
      _shops = null;
      _error = null;
      _loading = false;
    }

    if (rebuild && mounted) {
      setState(reset);
    } else {
      reset();
    }
  }

  Future<void> _loadShops({bool rebuild = true}) async {
    final request = ++_request;
    void start() {
      _loading = _shops == null;
      _error = null;
    }

    rebuild ? setState(start) : start();
    try {
      final shops = await context.api.getMyShops();
      if (!mounted || request != _request) return;
      setState(() => _shops = shops);
    } on Object catch (error) {
      if (!mounted || request != _request) return;
      setState(() => _error = error);
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _openLogin() async {
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  Future<void> _openRegister() async {
    final registered = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const RegisterScreen()),
    );
    // Like the web, a fresh account goes straight to the shop wizard.
    if (registered == true && mounted) await _openCreateShop();
  }

  Future<void> _openCreateShop() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => const CreateShopScreen()),
    );
    if (mounted && context.session.isAuthenticated) await _loadShops();
  }

  Future<void> _openShop(Shop shop) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => ShopDashboardScreen(shopId: shop.id, initialShop: shop),
      ),
    );
    if (mounted && context.session.isAuthenticated) await _loadShops();
  }

  Future<void> _openModeration() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => const ModerationScreen()),
    );
  }

  Future<void> _logout() async {
    final confirmed = await confirmAction(
      context,
      title: 'Se déconnecter',
      message: 'Voulez-vous vraiment vous déconnecter de votre espace marchand ?',
      confirmLabel: 'Se déconnecter',
    );
    if (!confirmed || !mounted) return;
    await context.session.logout();
    if (!mounted) return;
    showToast(context, 'Vous êtes déconnecté. À bientôt !');
  }

  @override
  Widget build(BuildContext context) {
    final session = context.session;
    return ListenableBuilder(
      listenable: session,
      builder: (context, _) => Scaffold(
        body: session.isAuthenticated ? _buildMerchant(session) : _buildGuest(),
      ),
    );
  }

  // ---------- Signed out ----------

  Widget _buildGuest() {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        _GuestHero(onLogin: _openLogin, onRegister: _openRegister),
        const Padding(
          padding: EdgeInsets.fromLTRB(20, 28, 20, 4),
          child: SectionHeading(eyebrow: 'Comment ça marche', title: 'Trois étapes, cinq minutes'),
        ),
        for (final (index, step) in _steps.indexed)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _StepCard(number: index + 1, title: step.$1, text: step.$2),
          ),
        const Padding(
          padding: EdgeInsets.fromLTRB(20, 32, 20, 12),
          child: SectionHeading(eyebrow: 'Inclus', title: 'Tout pour vendre en confiance'),
        ),
        for (final feature in _features)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: _FeatureTile(icon: feature.$1, title: feature.$2, text: feature.$3),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
          child: ElevatedButton.icon(
            onPressed: _openRegister,
            icon: const Icon(Icons.auto_awesome),
            label: const Text('Créer ma boutique gratuitement'),
          ),
        ),
      ],
    );
  }

  // ---------- Signed in ----------

  Widget _buildMerchant(Session session) {
    final user = session.user;
    final shops = _shops;
    final Widget content;
    if (_loading && shops == null) {
      content = const Padding(
        padding: EdgeInsets.symmetric(vertical: 48),
        child: LoadingView(text: 'Chargement de vos boutiques…'),
      );
    } else if (_error != null && shops == null) {
      content = MessageState(
        icon: Icons.cloud_off_outlined,
        title: 'Impossible de charger vos boutiques',
        message: errorText(_error!),
        onRetry: _loadShops,
      );
    } else if (shops == null || shops.isEmpty) {
      content = Card(
        child: MessageState(
          title: 'Vous n’avez pas encore de boutique',
          message: 'Décrivez votre activité en une phrase et l’IA crée votre boutique '
              'en quelques minutes.',
          action: ElevatedButton.icon(
            onPressed: _openCreateShop,
            icon: const Icon(Icons.auto_awesome),
            label: const Text('Créer ma première boutique'),
          ),
        ),
      );
    } else {
      content = Column(
        children: [
          for (final shop in shops)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ShopCard(shop: shop, onTap: () => _openShop(shop)),
            ),
        ],
      );
    }

    return RefreshIndicator(
      onRefresh: _loadShops,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          _MerchantHeader(
            name: user?.name,
            email: user?.email,
            isAdmin: session.isAdmin,
            onCreateShop: _openCreateShop,
          ),
          if (session.isAdmin)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: _ActionTile(
                icon: Icons.shield_outlined,
                title: 'Modération des produits',
                subtitle: 'Produits signalés par l’agent IA anti-fraude.',
                onTap: _openModeration,
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 26, 20, 14),
            child: SectionHeading(
              eyebrow: 'Tableau de bord',
              title: 'Mes boutiques',
              subtitle: 'Gérez vos boutiques, vos produits et vos commandes.',
              trailing: shops != null && shops.isNotEmpty
                  ? StatusPill(label: plural(shops.length, 'boutique'), tone: Tone.gold)
                  : null,
            ),
          ),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: content),
          const SizedBox(height: 20),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: OutlinedButton.icon(
              onPressed: _logout,
              style: OutlinedButton.styleFrom(
                foregroundColor: danger,
                side: BorderSide(color: danger.withValues(alpha: 0.35)),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Se déconnecter'),
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }
}

const _steps = [
  ('Décrivez', '« Je vends des pagnes wax et des sacs faits main à Abidjan. » Une phrase suffit.'),
  ('Personnalisez', 'Choisissez un modèle, ajustez vos couleurs. L’IA a déjà tout préparé.'),
  ('Vendez', 'Partagez votre lien sur WhatsApp, Facebook et TikTok. C’est en ligne !'),
];

const _features = [
  (
    Icons.auto_awesome,
    'Assistant IA',
    'Décrivez votre activité en une phrase : l’IA propose le nom, les catégories, '
        'les textes et le design de votre boutique.',
  ),
  (
    Icons.forum_outlined,
    'Chatbot 24h/24',
    'Un vendeur virtuel répond à vos clients jour et nuit, recommande vos produits '
        'et rassure avant l’achat.',
  ),
  (
    Icons.verified_user_outlined,
    'Agent anti-fraude',
    'Chaque produit est vérifié par notre agent IA avant publication : '
        'vos clients achètent en confiance.',
  ),
  (
    Icons.phone_android,
    'Mobile Money',
    'Orange Money, MTN MoMo, Wave, Moov… et paiement à la livraison. '
        'Encaissez comme vos clients paient.',
  ),
];

/// Forest banner with the Kente motif shared by both states of the tab.
class _ForestBanner extends StatelessWidget {
  const _ForestBanner({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [forest, forestHover],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Stack(
        children: [
          const Positioned.fill(child: CustomPaint(painter: MotifPainter(opacity: 0.12))),
          SafeArea(
            bottom: false,
            child: Padding(padding: const EdgeInsets.fromLTRB(20, 16, 20, 26), child: child),
          ),
        ],
      ),
    );
  }
}

class _GuestHero extends StatelessWidget {
  const _GuestHero({required this.onLogin, required this.onRegister});

  final VoidCallback onLogin;
  final VoidCallback onRegister;

  @override
  Widget build(BuildContext context) {
    return _ForestBanner(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const BrandMark(light: true, compact: true),
          const SizedBox(height: 28),
          Text(
            'ESPACE MARCHAND',
            style: TextStyle(
              color: gold.withValues(alpha: 0.95),
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.6,
            ),
          ),
          const SizedBox(height: 8),
          Text.rich(
            TextSpan(
              children: [
                const TextSpan(text: 'Vendez en ligne avec ShopAI, '),
                TextSpan(
                  text: 'prêt en 5 minutes.',
                  style: headingStyle(
                    fontSize: 30,
                    color: gold,
                    height: 1.15,
                  ).copyWith(fontStyle: FontStyle.italic),
                ),
              ],
            ),
            style: headingStyle(fontSize: 30, color: Colors.white, height: 1.15),
          ),
          const SizedBox(height: 14),
          Text(
            'Décrivez votre activité en une phrase. ShopAI crée votre boutique, rédige vos '
            'fiches produits, vérifie chaque article et répond à vos clients jour et nuit.',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.82), height: 1.5),
          ),
          const SizedBox(height: 18),
          const Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _TrustChip(icon: Icons.phone_android, label: 'Mobile Money'),
              _TrustChip(icon: Icons.verified_user_outlined, label: 'Agent anti-fraude IA'),
              _TrustChip(icon: Icons.forum_outlined, label: 'Chatbot 24/7'),
            ],
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: onRegister,
            style: ElevatedButton.styleFrom(backgroundColor: gold, foregroundColor: ink),
            child: const Text('Créer un compte'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: onLogin,
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              minimumSize: const Size(48, 50),
              side: BorderSide(color: Colors.white.withValues(alpha: 0.45)),
            ),
            child: const Text('Se connecter'),
          ),
        ],
      ),
    );
  }
}

class _TrustChip extends StatelessWidget {
  const _TrustChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: gold.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: gold),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.number, required this.title, required this.text});

  final int number;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '0$number',
              style: headingStyle(fontSize: 30, color: gold, fontWeight: FontWeight.w700),
            ),
            const SizedBox(width: 14),
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
    );
  }
}

class _FeatureTile extends StatelessWidget {
  const _FeatureTile({required this.icon, required this.title, required this.text});

  final IconData icon;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(color: goldSoft, borderRadius: BorderRadius.circular(12)),
              child: Icon(icon, color: goldInk, size: 21),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  const SizedBox(height: 3),
                  Text(text, style: const TextStyle(color: muted, fontSize: 13, height: 1.4)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MerchantHeader extends StatelessWidget {
  const _MerchantHeader({
    required this.name,
    required this.email,
    required this.isAdmin,
    required this.onCreateShop,
  });

  final String? name;
  final String? email;
  final bool isAdmin;
  final VoidCallback onCreateShop;

  @override
  Widget build(BuildContext context) {
    final displayName = name == null || name!.isEmpty ? null : name;
    return _ForestBanner(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const BrandMark(light: true, compact: true),
              const Spacer(),
              if (isAdmin)
                const StatusPill(
                  label: 'Administrateur',
                  tone: Tone.gold,
                  icon: Icons.admin_panel_settings_outlined,
                ),
            ],
          ),
          const SizedBox(height: 26),
          Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: gold,
                child: Text(
                  (displayName ?? email ?? 'M').characters.first.toUpperCase(),
                  style: headingStyle(fontSize: 22, color: forest, fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      displayName == null ? 'Bonjour 👋' : 'Bonjour, $displayName 👋',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: headingStyle(fontSize: 24, color: Colors.white, height: 1.15),
                    ),
                    if (email != null && email!.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        email!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 13),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          ElevatedButton.icon(
            onPressed: onCreateShop,
            style: ElevatedButton.styleFrom(backgroundColor: gold, foregroundColor: ink),
            icon: const Icon(Icons.add),
            label: const Text('Créer une boutique'),
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        leading: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(color: forestSoft, borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: forest),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(subtitle, style: const TextStyle(color: muted, fontSize: 13)),
        trailing: const Icon(Icons.chevron_right, color: muted),
      ),
    );
  }
}

class _ShopCard extends StatelessWidget {
  const _ShopCard({required this.shop, required this.onTap});

  final Shop shop;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = shop.theme;
    final summary = shop.tagline ?? shop.description ?? '—';
    final count = shop.productCount ?? 0;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              height: 6,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [theme.primary, theme.secondary]),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ShopLogo(name: shop.name, logoUrl: shop.logoUrl, color: theme.primary, size: 52),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                shop.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: headingStyle(fontSize: 18),
                              ),
                            ),
                            const SizedBox(width: 8),
                            StatusPill(
                              label: shop.isActive ? 'En ligne' : 'Hors ligne',
                              tone: shop.isActive ? Tone.success : Tone.neutral,
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          summary,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: muted, fontSize: 13, height: 1.35),
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 14,
                          runSpacing: 6,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            _Meta(icon: Icons.inventory_2_outlined, label: plural(count, 'produit')),
                            _Meta(icon: theme.format.icon, label: theme.format.label),
                            _Meta(icon: Icons.payments_outlined, label: shop.currency),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.only(left: 4, top: 14),
                    child: Icon(Icons.chevron_right, color: muted),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: goldInk),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
      ],
    );
  }
}

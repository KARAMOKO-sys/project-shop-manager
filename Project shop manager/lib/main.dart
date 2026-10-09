import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/scope.dart';
import 'app/theme.dart';
import 'screens/account/account_screen.dart';
import 'screens/storefront/cart_screen.dart';
import 'screens/storefront/home_screen.dart';
import 'screens/storefront/templates_screen.dart';
import 'services/api_service.dart';
import 'state/session.dart';
import 'state/shop_cart.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final preferences = await SharedPreferences.getInstance();
  final api = ApiService();
  final session = await Session.restore(api, preferences);
  final cart = ShopCart.restore(preferences);
  runApp(ShopAiApp(api: api, session: session, cart: cart));
}

class ShopAiApp extends StatelessWidget {
  const ShopAiApp({required this.api, required this.session, required this.cart, super.key});

  final ApiService api;
  final Session session;
  final ShopCart cart;

  @override
  Widget build(BuildContext context) {
    return AppScope(
      api: api,
      session: session,
      cart: cart,
      child: MaterialApp(
        title: 'ShopAI',
        debugShowCheckedModeBanner: false,
        theme: buildAppTheme(),
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: const ShopShell(),
      ),
    );
  }
}

/// Bottom navigation: storefront, template gallery, cart and the merchant space.
class ShopShell extends StatefulWidget {
  const ShopShell({super.key});

  static const homeTab = 0;
  static const templatesTab = 1;
  static const cartTab = 2;
  static const accountTab = 3;

  /// Switches the root tab from anywhere below the shell (e.g. "Voir mon panier").
  static void selectTab(BuildContext context, int index) {
    final state = context.findAncestorStateOfType<_ShopShellState>();
    Navigator.of(context).popUntil((route) => route.isFirst);
    state?._select(index);
  }

  @override
  State<ShopShell> createState() => _ShopShellState();
}

class _ShopShellState extends State<ShopShell> {
  int _index = ShopShell.homeTab;

  void _select(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    final cart = context.cart;
    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: const [HomeScreen(), TemplatesScreen(), CartScreen(), AccountScreen()],
      ),
      bottomNavigationBar: ListenableBuilder(
        listenable: cart,
        builder: (context, _) => NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: _select,
          destinations: [
            const NavigationDestination(icon: Icon(Icons.storefront_outlined), selectedIcon: Icon(Icons.storefront, color: forest), label: 'Accueil'),
            const NavigationDestination(icon: Icon(Icons.palette_outlined), selectedIcon: Icon(Icons.palette, color: forest), label: 'Modèles'),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: cart.itemCount > 0,
                backgroundColor: gold,
                label: Text('${cart.itemCount}'),
                child: const Icon(Icons.shopping_bag_outlined),
              ),
              selectedIcon: Badge(
                isLabelVisible: cart.itemCount > 0,
                backgroundColor: gold,
                label: Text('${cart.itemCount}'),
                child: const Icon(Icons.shopping_bag, color: forest),
              ),
              label: 'Panier',
            ),
            const NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person, color: forest), label: 'Mon espace'),
          ],
        ),
      ),
    );
  }
}

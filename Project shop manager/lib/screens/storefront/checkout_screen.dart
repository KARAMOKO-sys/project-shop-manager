import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/format.dart';
import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../state/shop_cart.dart';
import '../../widgets/common.dart';
import 'order_tracking_screen.dart';

/// Checkout of one shop's cart (`/boutique/{slug}/commande`), with promo codes.
class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({required this.shopSlug, super.key});

  final String shopSlug;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _notes = TextEditingController();
  final _couponCode = TextEditingController();

  late final ShopCart _cart = context.cart;
  Shop? _shop;
  PaymentMethod _paymentMethod = PaymentMethod.cashOnDelivery;
  bool _loadingShop = true;
  String? _shopError;
  Map<String, String> _errors = const {};
  String? _generalError;
  bool _submitting = false;
  Order? _order;
  String? _paymentUrl;

  CouponCheck? _coupon;
  String? _couponError;
  bool _checkingCoupon = false;
  double? _couponSubtotal;

  @override
  void initState() {
    super.initState();
    _cart.addListener(_onCartChanged);
    _loadShop();
  }

  Future<void> _loadShop() async {
    setState(() {
      _loadingShop = true;
      _shopError = null;
    });
    try {
      final shop = await context.api.getStorefront(widget.shopSlug);
      if (!mounted) return;
      setState(() {
        _shop = shop;
        final available = PaymentMethod.values
            .where((method) => shop.paymentMethods.contains(method.value))
            .toList();
        if (!available.contains(_paymentMethod) && available.isNotEmpty) {
          _paymentMethod = available.first;
        }
      });
    } on ApiException catch (error) {
      if (mounted) setState(() => _shopError = error.message);
    } finally {
      if (mounted) setState(() => _loadingShop = false);
    }
  }

  @override
  void dispose() {
    _cart.removeListener(_onCartChanged);
    for (final controller in [_firstName, _lastName, _phone, _email, _notes, _couponCode]) {
      controller.dispose();
    }
    super.dispose();
  }

  double get _subtotal => _cart.subtotalForShop(widget.shopSlug);
  List<PaymentMethod> get _availablePaymentMethods => PaymentMethod.values
      .where((method) => _shop?.paymentMethods.contains(method.value) ?? false)
      .toList();

  /// Any cart change invalidates the computed discount: the customer must re-apply the code.
  void _onCartChanged() {
    if (_coupon != null && _couponSubtotal != _subtotal) {
      setState(() {
        _coupon = null;
        _couponError = 'Votre panier a changé : appliquez à nouveau votre code promo.';
      });
    } else {
      setState(() {});
    }
  }

  Future<void> _applyCoupon() async {
    final code = _couponCode.text.trim();
    setState(() {
      _couponError = null;
      _errors = {..._errors}..remove('coupon_code');
    });
    if (code.isEmpty) {
      setState(() => _couponError = 'Saisissez un code promo.');
      return;
    }
    setState(() => _checkingCoupon = true);
    try {
      final subtotal = _subtotal;
      final coupon = await context.api.checkCoupon(widget.shopSlug, code, subtotal);
      if (!mounted) return;
      setState(() {
        _coupon = coupon;
        _couponSubtotal = subtotal;
        _couponCode.text = coupon.code;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _coupon = null;
        _couponError = error is ApiException
            ? error.fieldError('code') ?? error.message
            : 'Ce code promo n’est pas valable.';
      });
    } finally {
      if (mounted) setState(() => _checkingCoupon = false);
    }
  }

  void _removeCoupon() => setState(() {
    _coupon = null;
    _couponCode.clear();
    _couponError = null;
  });

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    final cart = context.cart;
    final items = cart.itemsForShop(widget.shopSlug);
    setState(() {
      _submitting = true;
      _errors = const {};
      _generalError = null;
    });
    try {
      final result = await context.api.checkout(
        shopSlug: widget.shopSlug,
        customer: {
          'first_name': _firstName.text.trim(),
          'last_name': _lastName.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
        },
        items: [for (final item in items) {'product_id': item.productId, 'quantity': item.quantity}],
        paymentMethod: _paymentMethod.value,
        couponCode: _coupon?.code ?? (_couponCode.text.trim().isEmpty ? null : _couponCode.text.trim()),
        notes: _notes.text,
      );
      if (!mounted) return;
      cart.removeListener(_onCartChanged);
      final paymentUrl = _validatedPaymentUrl(result.paymentUrl);
      setState(() {
        _order = result.order;
        _paymentUrl = paymentUrl;
        if (result.order.paymentMethod == PaymentMethod.flutterwave.value && paymentUrl == null) {
          _generalError =
              'La commande est créée, mais le lien de paiement est indisponible. Contactez la boutique avec votre numéro de commande.';
        }
      });
      await cart.clearShop(widget.shopSlug);
      if (paymentUrl != null) await _openPaymentLink();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errors = error.fieldErrors;
        if (error.fieldErrors.isEmpty || error.fieldError('items') != null) {
          _generalError = error.fieldError('items') ?? error.message;
        }
        if (error.fieldError('coupon_code') case final message?) _couponError = message;
      });
      _formKey.currentState!.validate();
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final shopTheme = _shop?.theme;
    final page = _order == null ? _form() : _confirmation(_order!);
    return shopTheme == null ? page : Theme(data: buildShopTheme(context, shopTheme), child: page);
  }

  Widget _form() {
    if (_loadingShop) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_shopError != null || _shop == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Finaliser la commande')),
        body: MessageState(
          icon: Icons.wifi_off_outlined,
          title: 'Boutique indisponible',
          message: _shopError ?? 'Les informations de paiement n’ont pas pu être chargées.',
          action: ElevatedButton(onPressed: _loadShop, child: const Text('Réessayer')),
        ),
      );
    }
    final cart = context.cart;
    final items = cart.itemsForShop(widget.shopSlug);
    final accent = _shop?.theme.primary ?? forest;
    final currency = _shop?.currency ?? (items.isEmpty ? 'XOF' : items.first.currency);
    final subtotal = _subtotal;
    final discount = (_coupon?.discountAmount ?? 0).clamp(0, subtotal).toDouble();
    final total = (subtotal - discount).clamp(0, double.infinity).toDouble();

    return Scaffold(
      appBar: AppBar(title: const Text('Finaliser la commande')),
      body: items.isEmpty
          ? MessageState(
              icon: Icons.shopping_bag_outlined,
              title: 'Votre panier est vide',
              message: 'Ajoutez des produits avant de passer commande.',
              action: ElevatedButton(onPressed: () => openShop(context, widget.shopSlug), child: const Text('Retour à la boutique')),
            )
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                children: [
                  if (_shop != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        children: [
                          ShopLogo(name: _shop!.name, logoUrl: _shop!.logoUrl, color: accent, size: 32),
                          const SizedBox(width: 10),
                          Expanded(child: Text(_shop!.name, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17))),
                        ],
                      ),
                    ),
                  FormSection(
                    title: 'Vos coordonnées',
                    children: [
                      Row(
                        children: [
                          Expanded(child: _field(_firstName, 'customer.first_name', 'Prénom', autofill: AutofillHints.givenName)),
                          const SizedBox(width: 10),
                          Expanded(child: _field(_lastName, 'customer.last_name', 'Nom', autofill: AutofillHints.familyName)),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _field(_phone, 'customer.phone', 'Téléphone', keyboard: TextInputType.phone, required: false, autofill: AutofillHints.telephoneNumber),
                      const SizedBox(height: 12),
                      _field(_email, 'customer.email', 'E-mail', keyboard: TextInputType.emailAddress, autofill: AutofillHints.email),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _notes,
                        maxLength: 1000,
                        maxLines: 2,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(labelText: 'Instructions de livraison (facultatif)'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  FormSection(
                    title: 'Mode de paiement',
                    children: [
                      for (final method in _availablePaymentMethods)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _paymentOption(method, accent),
                        ),
                      if (_availablePaymentMethods.isEmpty)
                        const Text(
                          'Aucun moyen de paiement n’est actuellement activé pour cette boutique.',
                          style: TextStyle(color: muted),
                        ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  FormSection(
                    title: 'Récapitulatif',
                    children: [
                      for (final item in items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Row(
                            children: [
                              Expanded(child: Text('${item.quantity} × ${item.productName}')),
                              Text(formatPrice(item.price * item.quantity, currency), style: const TextStyle(fontWeight: FontWeight.w600)),
                            ],
                          ),
                        ),
                      const Divider(height: 18),
                      _couponBlock(accent),
                      const SizedBox(height: 10),
                      InfoRow(label: 'Sous-total', value: formatPrice(subtotal, currency)),
                      if (discount > 0)
                        InfoRow(label: 'Remise (${_coupon!.code})', value: '−${formatPrice(discount, currency)}', color: success),
                      const InfoRow(label: 'Livraison', value: 'Calculée par la boutique'),
                      const Divider(height: 16),
                      InfoRow(label: 'Total estimé', value: formatPrice(total, currency), bold: true, color: accent),
                    ],
                  ),
                  if (_generalError != null) ...[
                    const SizedBox(height: 14),
                    NoticeBanner(message: _generalError!),
                  ],
                  const SizedBox(height: 18),
                  LoadingButton(
                    label: 'Confirmer la commande · ${formatPrice(total, currency)}',
                    icon: Icons.lock_outline,
                    loading: _submitting,
                    onPressed: _availablePaymentMethods.isEmpty ? null : _submit,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Paiement sécurisé. Aucune donnée de carte ne transite par nos serveurs.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: muted, fontSize: 12),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String key,
    String label, {
    TextInputType? keyboard,
    bool required = true,
    String? autofill,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboard,
      autofillHints: autofill == null ? null : [autofill],
      textCapitalization: keyboard == null ? TextCapitalization.words : TextCapitalization.none,
      decoration: InputDecoration(labelText: required ? label : '$label (facultatif)'),
      onChanged: (_) {
        if (_errors.containsKey(key)) setState(() => _errors = {..._errors}..remove(key));
      },
      validator: (value) {
        if (_errors[key] case final message?) return message;
        final text = value?.trim() ?? '';
        if (required && text.isEmpty) return 'Ce champ est obligatoire.';
        if (keyboard == TextInputType.emailAddress && !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text)) {
          return 'Saisissez une adresse e-mail valide.';
        }
        return null;
      },
    );
  }

  Widget _paymentOption(PaymentMethod method, Color accent) {
    final selected = _paymentMethod == method;
    return Material(
      color: selected ? accent.withValues(alpha: 0.07) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: selected ? accent : border, width: selected ? 1.5 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => setState(() => _paymentMethod = method),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Icon(method.icon, color: accent),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(method.label, style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 2),
                    Text(method.description, style: const TextStyle(color: muted, fontSize: 12.5)),
                  ],
                ),
              ),
              Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off, color: selected ? accent : muted),
            ],
          ),
        ),
      ),
    );
  }

  Widget _couponBlock(Color accent) {
    if (_coupon case final coupon?) {
      return Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: successSoft, borderRadius: BorderRadius.circular(12)),
        child: Row(
          children: [
            const Icon(Icons.local_offer_outlined, color: successInk, size: 19),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Code ${coupon.code} appliqué',
                style: const TextStyle(color: successInk, fontWeight: FontWeight.w700),
              ),
            ),
            TextButton(onPressed: _removeCoupon, child: const Text('Retirer')),
          ],
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: TextField(
                controller: _couponCode,
                textCapitalization: TextCapitalization.characters,
                onSubmitted: (_) => _applyCoupon(),
                decoration: InputDecoration(
                  labelText: 'Code promo',
                  prefixIcon: const Icon(Icons.local_offer_outlined),
                  errorText: _couponError,
                  errorMaxLines: 3,
                ),
              ),
            ),
            const SizedBox(width: 8),
            OutlinedButton(
              onPressed: _checkingCoupon ? null : _applyCoupon,
              style: OutlinedButton.styleFrom(minimumSize: const Size(80, 48)),
              child: _checkingCoupon
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Appliquer'),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _copyPaymentLink() async {
    final paymentUrl = _paymentUrl;
    if (paymentUrl == null) return;
    await Clipboard.setData(ClipboardData(text: paymentUrl));
    if (mounted) showToast(context, 'Lien de paiement copié. Ouvrez-le dans votre navigateur.');
  }

  Future<void> _openPaymentLink() async {
    final paymentUrl = _paymentUrl;
    if (paymentUrl == null) return;
    try {
      final launched = await launchUrl(
        Uri.parse(paymentUrl),
        mode: LaunchMode.externalApplication,
      );
      if (!launched && mounted) {
        setState(() => _generalError = 'Le paiement n’a pas pu s’ouvrir. Copiez le lien pour continuer dans votre navigateur.');
      }
    } on PlatformException {
      if (mounted) {
        setState(() => _generalError = 'Le paiement n’a pas pu s’ouvrir. Copiez le lien pour continuer dans votre navigateur.');
      }
    } on MissingPluginException {
      if (mounted) {
        setState(() => _generalError = 'Le paiement n’a pas pu s’ouvrir. Copiez le lien pour continuer dans votre navigateur.');
      }
    }
  }

  Widget _confirmation(Order order) {
    final accent = _shop?.theme.primary ?? forest;
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(order.paymentMethod == PaymentMethod.flutterwave.value
            ? 'Paiement en attente'
            : 'Commande confirmée'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          const SizedBox(height: 12),
          Center(
            child: Container(
              width: 80,
              height: 80,
              decoration: const BoxDecoration(color: successSoft, shape: BoxShape.circle),
              child: Icon(
                order.paymentMethod == PaymentMethod.flutterwave.value
                    ? Icons.pending_outlined
                    : Icons.check_circle_outline,
                color: order.paymentMethod == PaymentMethod.flutterwave.value ? goldInk : success,
                size: 44,
              ),
            ),
          ),
          const SizedBox(height: 18),
          Text(
            order.paymentMethod == PaymentMethod.flutterwave.value
                ? 'Votre commande est enregistrée.'
                : 'Merci pour votre commande !',
            textAlign: TextAlign.center,
            style: headingStyle(fontSize: 25),
          ),
          const SizedBox(height: 8),
          Text(
            order.paymentMethod == PaymentMethod.flutterwave.value
                ? 'Terminez le paiement sécurisé pour confirmer votre commande.'
                : 'Un récapitulatif a été envoyé à ${order.customer.email}.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: muted),
          ),
          if (_generalError != null) ...[
            const SizedBox(height: 14),
            NoticeBanner(message: _generalError!),
          ],
          if (_paymentUrl != null) ...[
            const SizedBox(height: 14),
            OutlinedButton.icon(
              onPressed: _openPaymentLink,
              icon: const Icon(Icons.open_in_new),
              label: const Text('Ouvrir le paiement'),
            ),
            TextButton.icon(
              onPressed: _copyPaymentLink,
              icon: const Icon(Icons.copy_outlined),
              label: const Text('Copier le lien de paiement'),
            ),
          ],
          const SizedBox(height: 20),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                children: [
                  const Text('Numéro de commande', style: TextStyle(color: muted, fontSize: 12.5)),
                  const SizedBox(height: 4),
                  SelectableText('#${order.orderNumber}', style: TextStyle(color: accent, fontSize: 22, fontWeight: FontWeight.w800)),
                  const Divider(height: 26),
                  InfoRow(label: 'Sous-total', value: formatPrice(order.subtotal, order.currency)),
                  if (order.discountAmount > 0)
                    InfoRow(label: 'Remise', value: '−${formatPrice(order.discountAmount, order.currency)}', color: success),
                  InfoRow(label: 'Livraison', value: formatPrice(order.shippingAmount, order.currency)),
                  InfoRow(label: 'Total', value: formatPrice(order.total, order.currency), bold: true),
                  InfoRow(label: 'Paiement', value: PaymentMethod.labelOf(order.paymentMethod)),
                  InfoRow(
                    label: 'Statut du paiement',
                    value: order.paymentStatus == 'paid' ? 'Payé' : 'En attente',
                  ),
                  InfoRow(label: 'Statut de la commande', value: order.status.label),
                ],
              ),
            ),
          ),
          const SizedBox(height: 22),
          ElevatedButton.icon(
            onPressed: _shop == null
                ? null
                : () => Navigator.of(context).pushReplacement(
                    MaterialPageRoute(
                      builder: (_) => OrderTrackingScreen(shop: _shop!, orderNumber: order.orderNumber, email: order.customer.email),
                    ),
                  ),
            icon: const Icon(Icons.local_shipping_outlined),
            label: const Text('Suivre ma commande'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Continuer mes achats')),
        ],
      ),
    );
  }
}

String? _validatedPaymentUrl(String? value) {
  final uri = value == null ? null : Uri.tryParse(value);
  if (uri == null ||
      uri.scheme != 'https' ||
      uri.userInfo.isNotEmpty ||
      !(uri.host == 'flutterwave.com' || uri.host.endsWith('.flutterwave.com'))) {
    return null;
  }
  return uri.toString();
}

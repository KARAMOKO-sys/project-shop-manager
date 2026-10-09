import 'package:flutter/material.dart';

import '../../app/format.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';

/// Customer order tracking (`/boutique/{slug}/suivi`): order number + email as proof.
class OrderTrackingScreen extends StatefulWidget {
  const OrderTrackingScreen({required this.shop, this.orderNumber, this.email, super.key});

  final Shop shop;
  final String? orderNumber;
  final String? email;

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  late final _orderNumber = TextEditingController(text: widget.orderNumber);
  late final _email = TextEditingController(text: widget.email);
  final _formKey = GlobalKey<FormState>();
  String? _emailError;
  String? _searchError;
  bool _searching = false;
  Order? _order;

  @override
  void initState() {
    super.initState();
    if ((widget.orderNumber ?? '').isNotEmpty && (widget.email ?? '').isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _search());
    }
  }

  @override
  void dispose() {
    _orderNumber.dispose();
    _email.dispose();
    super.dispose();
  }

  String _normalize(String value) => value.trim().replaceFirst(RegExp('^#'), '').toUpperCase();

  Future<void> _search() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _emailError = null;
      _searchError = null;
    });
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _searching = true;
      _order = null;
    });
    try {
      final order = await context.api.trackOrder(widget.shop.slug, _normalize(_orderNumber.text), _email.text.trim());
      if (mounted) setState(() => _order = order);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        if (error.fieldError('email') case final message?) {
          _emailError = message;
        } else if (error.isNotFound) {
          _searchError = 'Aucune commande ne correspond à ce numéro et à cette adresse e-mail. Vérifiez vos informations.';
        } else {
          _searchError = error.message;
        }
      });
      _formKey.currentState!.validate();
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: buildShopTheme(context, widget.shop.theme),
      child: Builder(
        builder: (context) => Scaffold(
          appBar: AppBar(title: const Text('Suivre ma commande')),
          body: ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
            children: [
              Text(
                'Saisissez votre numéro de commande et l’adresse e-mail utilisée lors de l’achat chez ${widget.shop.name}.',
                style: const TextStyle(color: muted, height: 1.4),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        TextFormField(
                          controller: _orderNumber,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: 'Numéro de commande',
                            hintText: 'Ex. : ORD-20261006-ABCD',
                            prefixIcon: Icon(Icons.receipt_long_outlined),
                          ),
                          validator: (value) => _normalize(value ?? '').isEmpty ? 'Indiquez votre numéro de commande.' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          autofillHints: const [AutofillHints.email],
                          decoration: const InputDecoration(labelText: 'E-mail', prefixIcon: Icon(Icons.mail_outline)),
                          onFieldSubmitted: (_) => _search(),
                          validator: (value) {
                            if (_emailError != null) return _emailError;
                            return (value ?? '').trim().isEmpty ? 'Indiquez l’adresse e-mail utilisée lors de la commande.' : null;
                          },
                        ),
                        const SizedBox(height: 14),
                        LoadingButton(label: 'Rechercher', icon: Icons.search, loading: _searching, onPressed: _search),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              if (_searching)
                const Padding(padding: EdgeInsets.all(24), child: LoadingView(text: 'Recherche de votre commande…'))
              else if (_searchError != null)
                NoticeBanner(message: _searchError!, tone: Tone.warning, icon: Icons.warning_amber_rounded)
              else if (_order != null)
                _result(_order!),
            ],
          ),
        ),
      ),
    );
  }

  Widget _result(Order order) {
    final accent = widget.shop.theme.primary;
    final current = OrderStatus.timeline.indexOf(order.status);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Commande #${order.orderNumber}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                      const SizedBox(height: 2),
                      Text('Passée le ${formatDate(order.createdAt)}', style: const TextStyle(color: muted, fontSize: 12.5)),
                    ],
                  ),
                ),
                OrderStatusPill(order.status),
              ],
            ),
            const SizedBox(height: 18),
            if (order.status == OrderStatus.cancelled)
              const NoticeBanner(
                message: 'Cette commande a été annulée. Contactez la boutique si vous pensez qu’il s’agit d’une erreur.',
                icon: Icons.cancel_outlined,
              )
            else
              for (final (index, step) in OrderStatus.timeline.indexed)
                _timelineStep(step, done: index < current, isCurrent: index == current, isLast: index == OrderStatus.timeline.length - 1, accent: accent),
            const SizedBox(height: 14),
            const Text('Articles', style: TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            for (final item in order.items)
              InfoRow(label: '${item.quantity} × ${item.productName}', value: formatPrice(item.totalPrice, order.currency)),
            const Divider(height: 20),
            InfoRow(label: 'Sous-total', value: formatPrice(order.subtotal, order.currency)),
            InfoRow(label: 'Livraison', value: formatPrice(order.shippingAmount, order.currency)),
            if (order.discountAmount > 0)
              InfoRow(label: 'Remise', value: '−${formatPrice(order.discountAmount, order.currency)}', color: success),
            InfoRow(label: 'Total', value: formatPrice(order.total, order.currency), bold: true),
            InfoRow(label: 'Paiement', value: PaymentMethod.labelOf(order.paymentMethod), color: muted),
          ],
        ),
      ),
    );
  }

  Widget _timelineStep(OrderStatus step, {required bool done, required bool isCurrent, required bool isLast, required Color accent}) {
    final reached = done || isCurrent;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 28,
            child: Column(
              children: [
                Container(
                  width: 26,
                  height: 26,
                  decoration: BoxDecoration(
                    color: reached ? accent : Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: reached ? accent : border, width: 2),
                    boxShadow: isCurrent ? [BoxShadow(color: accent.withValues(alpha: 0.3), blurRadius: 8, spreadRadius: 2)] : null,
                  ),
                  child: reached ? Icon(Icons.check, size: 15, color: contrastingText(accent)) : null,
                ),
                if (!isLast) Expanded(child: Container(width: 2, color: done ? accent : border)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 3, bottom: 18),
              child: Text(
                step.label,
                style: TextStyle(
                  fontWeight: isCurrent ? FontWeight.w800 : FontWeight.w500,
                  color: reached ? null : muted,
                ),
              ),
            ),
          ),
          if (isCurrent) const Padding(padding: EdgeInsets.only(top: 2), child: StatusPill(label: 'Étape actuelle', tone: Tone.gold)),
        ],
      ),
    );
  }
}

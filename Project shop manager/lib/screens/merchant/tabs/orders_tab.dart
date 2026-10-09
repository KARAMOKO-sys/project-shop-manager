import 'package:flutter/material.dart';

import '../../../app/format.dart';
import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';

/// Order management, mirroring `components/dashboard/ShopOrders.vue`.
class OrdersTab extends StatefulWidget {
  const OrdersTab({required this.shop, super.key});

  final Shop shop;

  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> with AutomaticKeepAliveClientMixin {
  final List<Order> _orders = [];
  final Set<int> _expanded = {};
  final Set<int> _updating = {};
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
      if (_orders.isEmpty) _loading = true;
    });
    try {
      final page = await context.api.getShopOrders(widget.shop.id);
      if (!mounted) return;
      setState(() {
        _orders
          ..clear()
          ..addAll(page.items);
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on Object catch (error) {
      if (!mounted) return;
      if (_orders.isEmpty) {
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
      final page = await context.api.getShopOrders(widget.shop.id, page: _page + 1);
      if (!mounted) return;
      setState(() {
        final known = _orders.map((order) => order.id).toSet();
        _orders.addAll(page.items.where((order) => !known.contains(order.id)));
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

  void _put(Order order) {
    final index = _orders.indexWhere((item) => item.id == order.id);
    if (index >= 0) _orders[index] = order;
  }

  Future<void> _pickStatus(Order order) async {
    final options = [order.status, ...order.status.transitions];
    final next = await showModalBottomSheet<OrderStatus>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 4),
              child: Text('Statut de la commande', style: headingStyle(fontSize: 19)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text(
                'Commande #${order.orderNumber}',
                style: const TextStyle(color: muted, fontSize: 13),
              ),
            ),
            for (final status in options)
              ListTile(
                leading: Icon(
                  status == order.status ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                  color: status == order.status ? forest : muted,
                ),
                title: Align(alignment: Alignment.centerLeft, child: OrderStatusPill(status)),
                trailing: status == OrderStatus.cancelled
                    ? const Icon(Icons.block, color: danger, size: 20)
                    : null,
                onTap: () => Navigator.pop(context, status),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (next == null || next == order.status || !mounted) return;
    if (next == OrderStatus.cancelled) {
      final confirmed = await confirmAction(
        context,
        title: 'Annuler la commande ?',
        message:
            'La commande #${order.orderNumber} sera marquée comme annulée. '
            'Cette action ne peut pas être défaite.',
        confirmLabel: 'Confirmer l’annulation',
      );
      if (!confirmed || !mounted) return;
    }
    await _changeStatus(order, next);
  }

  Future<void> _changeStatus(Order order, OrderStatus next) async {
    final previous = order;
    setState(() {
      _put(_withStatus(order, next));
      _updating.add(order.id);
    });
    try {
      final updated = await context.api.updateOrderStatus(widget.shop.id, order.id, next);
      if (!mounted) return;
      setState(() => _put(updated));
      showToast(context, 'Commande ${order.orderNumber} : ${next.label.toLowerCase()}.');
    } on Object catch (error) {
      if (!mounted) return;
      setState(() => _put(previous));
      final message = error is ApiException ? error.fieldError('status') : null;
      showToast(context, message ?? errorText(error, 'Le statut n’a pas pu être modifié.'), error: true);
    } finally {
      if (mounted) setState(() => _updating.remove(order.id));
    }
  }

  static Order _withStatus(Order order, OrderStatus status) => Order(
    id: order.id,
    orderNumber: order.orderNumber,
    status: status,
    subtotal: order.subtotal,
    shippingAmount: order.shippingAmount,
    discountAmount: order.discountAmount,
    total: order.total,
    currency: order.currency,
    paymentMethod: order.paymentMethod,
    paymentStatus: order.paymentStatus,
    customer: order.customer,
    items: order.items,
    createdAt: order.createdAt,
  );

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (_loading && _orders.isEmpty) return const LoadingView(text: 'Chargement des commandes…');

    if (_orders.isEmpty) {
      return RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (_error != null)
              MessageState(
                title: 'Impossible de charger les commandes',
                message: _error,
                icon: Icons.receipt_long_outlined,
                onRetry: _refresh,
              )
            else
              const MessageState(
                title: 'Aucune commande pour l’instant',
                message:
                    'Partagez le lien de votre boutique sur WhatsApp et les réseaux sociaux '
                    'pour recevoir vos premières commandes.',
                icon: Icons.receipt_long_outlined,
              ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        itemCount: _orders.length + 2,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Text.rich(
              TextSpan(
                children: [
                  const TextSpan(text: 'Commandes '),
                  TextSpan(
                    text: '(${formatNumber(_total)})',
                    style: headingStyle(fontSize: 20, color: muted, fontWeight: FontWeight.w400),
                  ),
                ],
              ),
              style: headingStyle(fontSize: 20),
            );
          }
          if (index == _orders.length + 1) {
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
          final order = _orders[index - 1];
          return _OrderCard(
            order: order,
            expanded: _expanded.contains(order.id),
            updating: _updating.contains(order.id),
            onToggle: () => setState(() {
              if (!_expanded.remove(order.id)) _expanded.add(order.id);
            }),
            onChangeStatus: () => _pickStatus(order),
          );
        },
      ),
    );
  }
}

String _paymentStatusLabel(String status) => switch (status) {
  'pending' => 'En attente',
  'paid' => 'Payé',
  'failed' => 'Échoué',
  'refunded' => 'Remboursé',
  _ => status,
};

class _OrderCard extends StatelessWidget {
  const _OrderCard({
    required this.order,
    required this.expanded,
    required this.updating,
    required this.onToggle,
    required this.onChangeStatus,
  });

  final Order order;
  final bool expanded;
  final bool updating;
  final VoidCallback onToggle;
  final VoidCallback onChangeStatus;

  @override
  Widget build(BuildContext context) {
    final customer = order.customer;
    final canChange = order.status.transitions.isNotEmpty;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onToggle,
        child: Padding(
          padding: const EdgeInsets.all(14),
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
                        Text(
                          '#${order.orderNumber}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          formatDate(order.createdAt),
                          style: const TextStyle(color: muted, fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                  _StatusControl(
                    status: order.status,
                    enabled: canChange,
                    updating: updating,
                    onTap: onChangeStatus,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 1),
                    child: Icon(Icons.person_outline, size: 18, color: goldInk),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          customer.fullName.isEmpty ? 'Client' : customer.fullName,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        if (customer.phone != null)
                          SelectableText(customer.phone!, style: const TextStyle(color: muted, fontSize: 13)),
                        if (customer.email.isNotEmpty)
                          SelectableText(customer.email, style: const TextStyle(color: muted, fontSize: 13)),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          PaymentMethod.labelOf(order.paymentMethod),
                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                        ),
                        if (order.paymentStatus.isNotEmpty)
                          Text(
                            _paymentStatusLabel(order.paymentStatus),
                            style: const TextStyle(color: muted, fontSize: 12),
                          ),
                      ],
                    ),
                  ),
                  PriceTag(amount: order.total, currency: order.currency, fontSize: 16),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    expanded
                        ? 'Masquer le détail'
                        : 'Voir le détail (${plural(order.items.length, 'article')})',
                    style: const TextStyle(color: forest, fontSize: 12.5, fontWeight: FontWeight.w600),
                  ),
                  Icon(expanded ? Icons.expand_less : Icons.expand_more, size: 18, color: forest),
                ],
              ),
              AnimatedSize(
                duration: const Duration(milliseconds: 200),
                alignment: Alignment.topCenter,
                child: expanded ? _OrderDetails(order: order) : const SizedBox(width: double.infinity),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusControl extends StatelessWidget {
  const _StatusControl({
    required this.status,
    required this.enabled,
    required this.updating,
    required this.onTap,
  });

  final OrderStatus status;
  final bool enabled;
  final bool updating;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    if (!enabled && !updating) return OrderStatusPill(status);
    final colors = toneColors(orderStatusTone(status));
    return Semantics(
      button: true,
      label: 'Modifier le statut : ${status.label}',
      child: InkWell(
        onTap: updating ? null : onTap,
        borderRadius: BorderRadius.circular(30),
        child: Container(
          padding: const EdgeInsets.fromLTRB(10, 5, 6, 5),
          decoration: BoxDecoration(
            color: colors.bg,
            borderRadius: BorderRadius.circular(30),
            border: Border.all(color: colors.fg.withValues(alpha: 0.25)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                status.label,
                style: TextStyle(color: colors.fg, fontSize: 12, fontWeight: FontWeight.w700),
              ),
              const SizedBox(width: 2),
              if (updating)
                Padding(
                  padding: const EdgeInsets.all(3),
                  child: SizedBox(
                    width: 12,
                    height: 12,
                    child: CircularProgressIndicator(strokeWidth: 1.6, color: colors.fg),
                  ),
                )
              else
                Icon(Icons.arrow_drop_down, size: 18, color: colors.fg),
            ],
          ),
        ),
      ),
    );
  }
}

class _OrderDetails extends StatelessWidget {
  const _OrderDetails({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final currency = order.currency;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: tertiary, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Articles', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          const SizedBox(height: 6),
          for (final item in order.items)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      '${item.quantity} × ${item.productName}',
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(formatPrice(item.totalPrice, currency), style: const TextStyle(fontSize: 13)),
                ],
              ),
            ),
          const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Divider()),
          const Text('Récapitulatif', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          const SizedBox(height: 4),
          InfoRow(label: 'Sous-total', value: formatPrice(order.subtotal, currency)),
          InfoRow(label: 'Livraison', value: formatPrice(order.shippingAmount, currency)),
          if (order.discountAmount > 0)
            InfoRow(
              label: 'Remise',
              value: '−${formatPrice(order.discountAmount, currency)}',
              color: successInk,
            ),
          InfoRow(label: 'Total', value: formatPrice(order.total, currency), bold: true),
        ],
      ),
    );
  }
}

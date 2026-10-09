import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../app/format.dart';
import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';

/// Shop statistics, mirroring `components/dashboard/ShopOverview.vue`.
class OverviewTab extends StatefulWidget {
  const OverviewTab({required this.shop, super.key});

  final Shop shop;

  @override
  State<OverviewTab> createState() => _OverviewTabState();
}

class _OverviewTabState extends State<OverviewTab> with AutomaticKeepAliveClientMixin {
  Analytics? _analytics;
  String? _error;
  bool _loading = true;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _error = null;
      if (_analytics == null) _loading = true;
    });
    try {
      final analytics = await context.api.getAnalytics(widget.shop.id);
      if (!mounted) return;
      setState(() => _analytics = analytics);
    } on Object catch (error) {
      if (!mounted) return;
      if (_analytics == null) {
        setState(() => _error = errorText(error));
      } else {
        showToast(context, errorText(error), error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final analytics = _analytics;
    if (analytics == null) {
      if (_loading) return const LoadingView(text: 'Calcul de vos statistiques…');
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            MessageState(
              title: 'Statistiques indisponibles',
              message: _error,
              icon: Icons.query_stats,
              onRetry: _load,
            ),
          ],
        ),
      );
    }

    final currency = widget.shop.currency;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          _KpiGrid(analytics: analytics, currency: currency),
          if (analytics.pendingModeration > 0) ...[
            const SizedBox(height: 16),
            NoticeBanner(
              tone: Tone.warning,
              icon: Icons.shield_outlined,
              message:
                  '${analytics.pendingModeration} produit(s) en cours de vérification par notre '
                  'agent IA. Ils seront visibles du public dès validation.',
            ),
          ],
          if (analytics.lowStockProducts.isNotEmpty) ...[
            const SizedBox(height: 16),
            _LowStockCard(products: analytics.lowStockProducts),
          ],
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: RevenueChart(days: analytics.revenueByDay, currency: currency),
            ),
          ),
          const SizedBox(height: 16),
          _StatusCard(counts: analytics.ordersByStatus),
        ],
      ),
    );
  }
}

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.analytics, required this.currency});

  final Analytics analytics;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final kpis = [
      (
        label: 'Chiffre d’affaires',
        value: formatPrice(analytics.revenue, currency),
        icon: Icons.payments_outlined,
        highlight: true,
      ),
      (
        label: 'Commandes',
        value: formatNumber(analytics.orders),
        icon: Icons.receipt_long_outlined,
        highlight: false,
      ),
      (
        label: 'Clients',
        value: formatNumber(analytics.customers),
        icon: Icons.people_outline,
        highlight: false,
      ),
      (
        label: 'Panier moyen',
        value: formatPrice(analytics.averageOrderValue, currency),
        icon: Icons.shopping_basket_outlined,
        highlight: false,
      ),
      (
        label: 'Produits',
        value: formatNumber(analytics.products),
        icon: Icons.inventory_2_outlined,
        highlight: false,
      ),
      (
        label: 'En vérification',
        value: formatNumber(analytics.pendingModeration),
        icon: Icons.shield_outlined,
        highlight: false,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 12.0;
        final columns = constraints.maxWidth >= 720 ? 3 : 2;
        final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final kpi in kpis)
              SizedBox(
                width: width,
                child: _KpiTile(label: kpi.label, value: kpi.value, icon: kpi.icon, highlight: kpi.highlight),
              ),
          ],
        );
      },
    );
  }
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.label, required this.value, required this.icon, required this.highlight});

  final String label;
  final String value;
  final IconData icon;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final foreground = highlight ? ivory : ink;
    return Card(
      color: highlight ? forest : null,
      shape: highlight ? RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)) : null,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: highlight ? Colors.white.withValues(alpha: 0.12) : goldSoft,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 19, color: highlight ? gold : goldInk),
            ),
            const SizedBox(height: 12),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
                color: highlight ? ivory.withValues(alpha: 0.75) : muted,
              ),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                maxLines: 1,
                style: headingStyle(fontSize: 21, color: foreground, fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LowStockCard extends StatelessWidget {
  const _LowStockCard({required this.products});

  final List<LowStockProduct> products;

  @override
  Widget build(BuildContext context) {
    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: warning.withValues(alpha: 0.35)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.warning_amber_rounded, color: warning, size: 20),
                const SizedBox(width: 6),
                Text('Stock faible', style: headingStyle(fontSize: 18)),
              ],
            ),
            const SizedBox(height: 4),
            const Text(
              'Réapprovisionnez ces produits avant qu’ils ne soient épuisés.',
              style: TextStyle(color: muted, fontSize: 13),
            ),
            const SizedBox(height: 8),
            for (final (index, product) in products.indexed) ...[
              if (index > 0) const Divider(),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 9),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        product.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                    const SizedBox(width: 8),
                    StatusPill(
                      label: product.stockQuantity <= 0
                          ? 'Épuisé'
                          : '${formatNumber(product.stockQuantity)} restant(s)',
                      tone: product.stockQuantity <= 0 ? Tone.danger : Tone.warning,
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.counts});

  final Map<OrderStatus, int> counts;

  @override
  Widget build(BuildContext context) {
    final total = counts.values.fold<int>(0, (sum, count) => sum + count);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Commandes par statut', style: headingStyle(fontSize: 18)),
            const SizedBox(height: 14),
            for (final status in OrderStatus.values) ...[
              _StatusRow(status: status, count: counts[status] ?? 0, total: total),
              if (status != OrderStatus.values.last) const SizedBox(height: 14),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusRow extends StatelessWidget {
  const _StatusRow({required this.status, required this.count, required this.total});

  final OrderStatus status;
  final int count;
  final int total;

  @override
  Widget build(BuildContext context) {
    final percent = total == 0 ? 0 : (count / total * 100).round();
    final tone = toneColors(orderStatusTone(status));
    // The neutral tone ink is too pale for a bar: fall back to the muted text color.
    final barColor = status == OrderStatus.received ? muted : tone.fg;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(status.label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
            ),
            Text('${formatNumber(count)} · $percent %', style: const TextStyle(color: muted, fontSize: 13)),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: percent / 100,
            minHeight: 8,
            color: barColor,
            backgroundColor: tone.bg,
          ),
        ),
      ],
    );
  }
}

/// Daily revenue bar chart, mirroring `components/dashboard/RevenueChart.vue`.
///
/// The best day is drawn in gold; tapping a bar shows its detail.
class RevenueChart extends StatefulWidget {
  const RevenueChart({required this.days, required this.currency, super.key});

  final List<RevenueDay> days;
  final String currency;

  @override
  State<RevenueChart> createState() => _RevenueChartState();
}

class _RevenueChartState extends State<RevenueChart> {
  int? _selected;

  static String _dayLabel(DateTime date) => formatShortDate(date).replaceFirst(RegExp(r' \d{4}$'), '');

  @override
  void didUpdateWidget(RevenueChart oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.days != widget.days) _selected = null;
  }

  void _selectAt(Offset position, double width) {
    final count = widget.days.length;
    if (count == 0) return;
    final index = (position.dx / width * count).floor().clamp(0, count - 1);
    setState(() => _selected = _selected == index ? null : index);
  }

  @override
  Widget build(BuildContext context) {
    final days = widget.days;
    final currency = widget.currency;
    final totalRevenue = days.fold<double>(0, (sum, day) => sum + day.revenue);
    final totalOrders = days.fold<int>(0, (sum, day) => sum + day.orders);
    final selected = _selected == null || _selected! >= days.length ? null : days[_selected!];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          days.isEmpty ? 'Chiffre d’affaires' : 'Chiffre d’affaires — ${days.length} derniers jours',
          style: headingStyle(fontSize: 18),
        ),
        const SizedBox(height: 4),
        Text(
          'Total : ${formatPrice(totalRevenue, currency)} · ${formatNumber(totalOrders)} commande(s)',
          style: const TextStyle(color: muted, fontSize: 13),
        ),
        const SizedBox(height: 10),
        if (days.isEmpty || totalRevenue <= 0)
          Container(
            height: 150,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: tertiary, borderRadius: BorderRadius.circular(12)),
            child: const Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.bar_chart_rounded, color: goldInk, size: 30),
                SizedBox(height: 6),
                Text('Aucune vente sur la période.', style: TextStyle(fontWeight: FontWeight.w600)),
                SizedBox(height: 2),
                Text(
                  'Vos ventes apparaîtront ici jour par jour.',
                  style: TextStyle(color: muted, fontSize: 12.5),
                ),
              ],
            ),
          )
        else ...[
          SizedBox(
            height: 22,
            child: Align(
              alignment: Alignment.centerLeft,
              child: selected == null
                  ? const Row(
                      children: [
                        _LegendDot(),
                        SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Meilleur jour · touchez une barre pour le détail',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: muted, fontSize: 12),
                          ),
                        ),
                      ],
                    )
                  : Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: '${_dayLabel(selected.date)} : ',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          TextSpan(
                            text:
                                '${formatPrice(selected.revenue, currency)} · '
                                '${plural(selected.orders, 'commande')}',
                          ),
                        ],
                      ),
                      style: const TextStyle(fontSize: 12.5),
                    ),
            ),
          ),
          const SizedBox(height: 6),
          LayoutBuilder(
            builder: (context, constraints) => GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTapDown: (details) => _selectAt(details.localPosition, constraints.maxWidth),
              onHorizontalDragUpdate: (details) {
                final count = days.length;
                final index = (details.localPosition.dx / constraints.maxWidth * count).floor().clamp(
                  0,
                  count - 1,
                );
                if (index != _selected) setState(() => _selected = index);
              },
              child: Semantics(
                label:
                    'Chiffre d’affaires des ${days.length} derniers jours : '
                    '${formatPrice(totalRevenue, currency)} pour ${formatNumber(totalOrders)} commande(s).',
                child: CustomPaint(
                  size: Size(constraints.maxWidth, 200),
                  painter: _RevenueChartPainter(
                    values: [for (final day in days) day.revenue],
                    labels: [for (final day in days) _dayLabel(day.date)],
                    scaleLabel: (max) => formatPrice(max, currency),
                    selected: _selected,
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot();

  @override
  Widget build(BuildContext context) => Container(
    width: 10,
    height: 10,
    decoration: BoxDecoration(color: gold, borderRadius: BorderRadius.circular(3)),
  );
}

class _RevenueChartPainter extends CustomPainter {
  _RevenueChartPainter({
    required this.values,
    required this.labels,
    required this.scaleLabel,
    required this.selected,
  });

  final List<double> values;
  final List<String> labels;
  final String Function(double max) scaleLabel;
  final int? selected;

  static const _plotTop = 22.0;
  static const _axisHeight = 22.0;

  /// Rounds the scale up to a "nice" value so the top gridline reads cleanly.
  double get _scaleMax {
    final max = values.fold<double>(0, math.max);
    if (max <= 0) return 1;
    final magnitude = math.pow(10, (math.log(max) / math.ln10).floor()).toDouble();
    return (max / magnitude).ceil() * magnitude;
  }

  void _text(Canvas canvas, String text, Offset anchor, {TextAlign align = TextAlign.left, double? y}) {
    final painter = TextPainter(
      text: TextSpan(
        text: text,
        style: const TextStyle(color: muted, fontSize: 11),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    final dx = switch (align) {
      TextAlign.center => anchor.dx - painter.width / 2,
      TextAlign.right || TextAlign.end => anchor.dx - painter.width,
      _ => anchor.dx,
    };
    painter.paint(canvas, Offset(dx, y ?? anchor.dy));
  }

  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final plotBottom = size.height - _axisHeight;
    final plotHeight = plotBottom - _plotTop;
    final scaleMax = _scaleMax;
    final maxValue = values.fold<double>(0, math.max);
    final peak = maxValue > 0 ? values.indexOf(maxValue) : -1;

    final grid = Paint()
      ..color = border
      ..strokeWidth = 1;
    canvas
      ..drawLine(const Offset(0, _plotTop), Offset(size.width, _plotTop), grid)
      ..drawLine(Offset(0, _plotTop + plotHeight / 2), Offset(size.width, _plotTop + plotHeight / 2), grid);
    _text(canvas, scaleLabel(scaleMax), Offset.zero, y: 2);

    final slot = size.width / values.length;
    final gap = slot > 8 ? 2.0 : 1.0;
    final barWidth = math.max(1.0, slot - gap);
    for (var index = 0; index < values.length; index++) {
      final value = values[index];
      if (value <= 0) continue;
      final height = math.max(2.0, value / scaleMax * plotHeight);
      final left = index * slot + gap / 2;
      final radius = math.min(4.0, math.min(barWidth / 2, height));
      final rect = RRect.fromRectAndCorners(
        Rect.fromLTWH(left, plotBottom - height, barWidth, height),
        topLeft: Radius.circular(radius),
        topRight: Radius.circular(radius),
      );
      final base = index == peak ? gold : forest;
      final dimmed = selected != null && selected != index;
      canvas.drawRRect(rect, Paint()..color = dimmed ? base.withValues(alpha: 0.45) : base);
    }

    if (selected != null && selected! < values.length) {
      final x = selected! * slot + slot / 2;
      canvas.drawLine(
        Offset(x, _plotTop),
        Offset(x, plotBottom),
        Paint()
          ..color = ink.withValues(alpha: 0.25)
          ..strokeWidth = 1,
      );
    }

    canvas.drawLine(
      Offset(0, plotBottom),
      Offset(size.width, plotBottom),
      Paint()
        ..color = muted.withValues(alpha: 0.5)
        ..strokeWidth = 1,
    );

    final count = values.length;
    final indexes = {0, (count - 1) ~/ 2, count - 1}.toList()..sort();
    final labelY = plotBottom + 6;
    for (final index in indexes) {
      final center = index * slot + slot / 2;
      final align = index == 0
          ? TextAlign.left
          : index == count - 1
          ? TextAlign.right
          : TextAlign.center;
      final anchorX = switch (align) {
        TextAlign.left => index * slot,
        TextAlign.right => (index + 1) * slot,
        _ => center,
      };
      _text(canvas, labels[index], Offset(anchorX, labelY), align: align);
    }
  }

  @override
  bool shouldRepaint(_RevenueChartPainter oldDelegate) =>
      oldDelegate.values != values || oldDelegate.selected != selected || oldDelegate.labels != labels;
}

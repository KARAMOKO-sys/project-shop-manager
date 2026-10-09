import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/format.dart';
import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';

/// Promo codes management, mirroring `components/dashboard/ShopCoupons.vue`.
class CouponsTab extends StatefulWidget {
  const CouponsTab({required this.shop, super.key});

  final Shop shop;

  @override
  State<CouponsTab> createState() => _CouponsTabState();
}

class _CouponsTabState extends State<CouponsTab> with AutomaticKeepAliveClientMixin {
  List<Coupon>? _coupons;
  final Set<int> _busy = {};
  bool _loading = true;
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
      if (_coupons == null) _loading = true;
    });
    try {
      final coupons = await context.api.getCoupons(widget.shop.id);
      if (!mounted) return;
      setState(() => _coupons = coupons);
    } on Object catch (error) {
      if (!mounted) return;
      if (_coupons == null) {
        setState(() => _error = errorText(error));
      } else {
        showToast(context, errorText(error), error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create() async {
    final coupon = await showModalBottomSheet<Coupon>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CouponFormSheet(shop: widget.shop),
    );
    if (coupon == null || !mounted) return;
    setState(() => _coupons = [coupon, ...?_coupons]);
    showToast(context, 'Code promo ${coupon.code} créé.');
  }

  Future<void> _toggle(Coupon coupon) async {
    final next = coupon.isActive ? 'inactive' : 'active';
    setState(() => _busy.add(coupon.id));
    try {
      final updated = await context.api.updateCoupon(widget.shop.id, coupon.id, {'status': next});
      if (!mounted) return;
      setState(
        () => _coupons = [
          for (final item in _coupons ?? const <Coupon>[]) item.id == updated.id ? updated : item,
        ],
      );
      showToast(context, 'Code ${updated.code} ${next == 'active' ? 'activé' : 'désactivé'}.');
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error, 'Le statut n’a pas pu être modifié.'), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(coupon.id));
    }
  }

  Future<void> _delete(Coupon coupon) async {
    final confirmed = await confirmAction(
      context,
      title: 'Supprimer le code promo ?',
      message: 'Supprimer définitivement le code promo « ${coupon.code} » ?',
    );
    if (!confirmed || !mounted) return;
    setState(() => _busy.add(coupon.id));
    try {
      await context.api.deleteCoupon(widget.shop.id, coupon.id);
      if (!mounted) return;
      setState(
        () => _coupons = (_coupons ?? const <Coupon>[]).where((item) => item.id != coupon.id).toList(),
      );
      showToast(context, 'Code promo supprimé.');
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error, 'La suppression a échoué.'), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(coupon.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final coupons = _coupons;
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: coupons == null || coupons.isEmpty
          ? null
          : FloatingActionButton.extended(
              heroTag: 'add-coupon-${widget.shop.id}',
              backgroundColor: forest,
              foregroundColor: ivory,
              onPressed: _create,
              icon: const Icon(Icons.add),
              label: const Text('Nouveau code promo'),
            ),
      body: _body(coupons),
    );
  }

  Widget _body(List<Coupon>? coupons) {
    if (_loading && coupons == null) return const LoadingView(text: 'Chargement des codes promo…');

    final header = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text.rich(
          TextSpan(
            children: [
              const TextSpan(text: 'Codes promo '),
              if (coupons != null)
                TextSpan(
                  text: '(${coupons.length})',
                  style: headingStyle(fontSize: 20, color: muted, fontWeight: FontWeight.w400),
                ),
            ],
          ),
          style: headingStyle(fontSize: 20),
        ),
        const SizedBox(height: 4),
        const Text(
          'Offrez des réductions à vos clients pour booster vos ventes.',
          style: TextStyle(color: muted, fontSize: 13),
        ),
      ],
    );

    if (coupons == null || coupons.isEmpty) {
      return RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            header,
            if (coupons == null)
              MessageState(
                title: 'Impossible de charger les codes promo',
                message: _error,
                icon: Icons.local_offer_outlined,
                onRetry: _refresh,
              )
            else
              MessageState(
                title: 'Aucun code promo',
                message: 'Créez un code comme BIENVENUE10 pour offrir 10 % à vos nouveaux clients.',
                icon: Icons.local_offer_outlined,
                action: ElevatedButton.icon(
                  onPressed: _create,
                  icon: const Icon(Icons.add),
                  label: const Text('Créer mon premier code'),
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
        itemCount: coupons.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == 0) return header;
          final coupon = coupons[index - 1];
          return _CouponCard(
            coupon: coupon,
            currency: widget.shop.currency,
            busy: _busy.contains(coupon.id),
            onToggle: () => _toggle(coupon),
            onDelete: () => _delete(coupon),
          );
        },
      ),
    );
  }
}

String _couponValue(Coupon coupon, String currency) =>
    coupon.isPercentage ? '${formatNumber(coupon.value, 2)} %' : formatPrice(coupon.value, currency);

String _validity(Coupon coupon) {
  if (coupon.startsAt != null && coupon.endsAt != null) {
    return 'Du ${formatShortDate(coupon.startsAt)} au ${formatShortDate(coupon.endsAt)}';
  }
  if (coupon.startsAt != null) return 'À partir du ${formatShortDate(coupon.startsAt)}';
  if (coupon.endsAt != null) return 'Jusqu’au ${formatShortDate(coupon.endsAt)}';
  return 'Sans limite de date';
}

String _usage(Coupon coupon) =>
    '${formatNumber(coupon.timesUsed)} / '
    '${coupon.usageLimit > 0 ? formatNumber(coupon.usageLimit) : 'illimité'}';

class _CouponCard extends StatelessWidget {
  const _CouponCard({
    required this.coupon,
    required this.currency,
    required this.busy,
    required this.onToggle,
    required this.onDelete,
  });

  final Coupon coupon;
  final String currency;
  final bool busy;
  final VoidCallback onToggle;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 6, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: goldSoft,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: gold.withValues(alpha: 0.5)),
                  ),
                  child: Text(
                    coupon.code,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontWeight: FontWeight.w800,
                      color: goldInk,
                      letterSpacing: 0.8,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '− ${_couponValue(coupon, currency)}',
                    style: headingStyle(fontSize: 18, color: forest, fontWeight: FontWeight.w700),
                  ),
                ),
                StatusPill(
                  label: coupon.isActive ? 'Actif' : 'Inactif',
                  tone: coupon.isActive ? Tone.success : Tone.neutral,
                ),
                const SizedBox(width: 8),
              ],
            ),
            const SizedBox(height: 12),
            _Line(
              icon: Icons.shopping_cart_outlined,
              text: coupon.minOrderAmount > 0
                  ? 'Minimum d’achat : ${formatPrice(coupon.minOrderAmount, currency)}'
                  : 'Minimum d’achat : aucun',
            ),
            _Line(icon: Icons.event_outlined, text: _validity(coupon)),
            _Line(icon: Icons.repeat, text: 'Utilisations : ${_usage(coupon)}'),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(
                  coupon.isRedeemable ? Icons.check_circle_outline : Icons.block,
                  size: 16,
                  color: coupon.isRedeemable ? success : muted,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    coupon.isRedeemable ? 'Utilisable' : 'Non utilisable',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: coupon.isRedeemable ? successInk : muted,
                    ),
                  ),
                ),
                if (busy)
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 14),
                    child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  )
                else
                  TextButton.icon(
                    onPressed: onToggle,
                    icon: Icon(
                      coupon.isActive ? Icons.pause_circle_outline : Icons.play_circle_outline,
                      size: 18,
                    ),
                    label: Text(coupon.isActive ? 'Désactiver' : 'Activer'),
                  ),
                IconButton(
                  tooltip: 'Supprimer ${coupon.code}',
                  onPressed: busy ? null : onDelete,
                  icon: const Icon(Icons.delete_outline, color: danger),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 5, right: 8),
    child: Row(
      children: [
        Icon(icon, size: 16, color: goldInk),
        const SizedBox(width: 8),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 13))),
      ],
    ),
  );
}

/// Creation form, opened in a bottom sheet. Pops the created [Coupon].
class _CouponFormSheet extends StatefulWidget {
  const _CouponFormSheet({required this.shop});

  final Shop shop;

  @override
  State<_CouponFormSheet> createState() => _CouponFormSheetState();
}

class _CouponFormSheetState extends State<_CouponFormSheet> {
  final _code = TextEditingController();
  final _value = TextEditingController();
  final _minOrder = TextEditingController();
  final _usageLimit = TextEditingController();
  String _type = 'percentage';
  DateTime? _startsAt;
  DateTime? _endsAt;
  bool _active = true;
  bool _saving = false;
  Map<String, String> _errors = const {};

  String get _unit => currencyUnit(widget.shop.currency);

  @override
  void dispose() {
    _code.dispose();
    _value.dispose();
    _minOrder.dispose();
    _usageLimit.dispose();
    super.dispose();
  }

  static double? _parse(String text) {
    final cleaned = text.replaceAll(RegExp(r'[\s  ]'), '').replaceAll(',', '.');
    return cleaned.isEmpty ? null : double.tryParse(cleaned);
  }

  Future<void> _pickDate({required bool start}) async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final current = start ? _startsAt : _endsAt;
    final first = start ? DateTime(now.year - 1) : (_startsAt ?? DateTime(now.year - 1));
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? (first.isAfter(today) ? first : today),
      firstDate: first,
      lastDate: DateTime(now.year + 5, 12, 31),
      helpText: start ? 'Date de début' : 'Date de fin',
      cancelText: 'Annuler',
      confirmText: 'Valider',
    );
    if (picked == null || !mounted) return;
    setState(() {
      if (start) {
        _startsAt = picked;
        if (_endsAt != null && _endsAt!.isBefore(picked)) _endsAt = null;
      } else {
        _endsAt = picked;
      }
    });
  }

  Map<String, String> _validate() {
    final errors = <String, String>{};
    final code = _code.text.trim();
    if (code.isEmpty) {
      errors['code'] = 'Indiquez un code.';
    } else if (!RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(code)) {
      errors['code'] = 'Lettres, chiffres, tirets et underscores uniquement.';
    }
    final value = _parse(_value.text);
    if (value == null || value <= 0) {
      errors['value'] = 'Indiquez une valeur supérieure à 0.';
    } else if (_type == 'percentage' && value > 100) {
      errors['value'] = 'Un pourcentage ne peut pas dépasser 100.';
    }
    final minOrder = _minOrder.text.trim();
    if (minOrder.isNotEmpty && (_parse(minOrder) ?? -1) < 0) {
      errors['min_order_amount'] = 'Indiquez un montant valide.';
    }
    final limit = _usageLimit.text.trim();
    if (limit.isNotEmpty && (int.tryParse(limit) ?? -1) < 0) {
      errors['usage_limit'] = 'Indiquez un nombre entier.';
    }
    return errors;
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    final localErrors = _validate();
    if (localErrors.isNotEmpty) {
      setState(() => _errors = localErrors);
      return;
    }
    setState(() {
      _saving = true;
      _errors = const {};
    });
    final body = <String, dynamic>{
      'code': _code.text.trim().toUpperCase(),
      'type': _type,
      'value': _parse(_value.text),
      'min_order_amount': _parse(_minOrder.text) ?? 0,
      'starts_at': _startsAt == null ? null : isoDate(_startsAt!),
      'ends_at': _endsAt == null ? null : isoDate(_endsAt!),
      'usage_limit': int.tryParse(_usageLimit.text.trim()) ?? 0,
      'status': _active ? 'active' : 'inactive',
    };
    try {
      final coupon = await context.api.createCoupon(widget.shop.id, body);
      if (!mounted) return;
      Navigator.pop(context, coupon);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) {
        showToast(context, errorText(error, 'Le code promo n’a pas pu être créé.'), error: true);
      }
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error, 'Le code promo n’a pas pu être créé.'), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _dateField({required bool start}) {
    final value = start ? _startsAt : _endsAt;
    final key = start ? 'starts_at' : 'ends_at';
    return InkWell(
      onTap: () => _pickDate(start: start),
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        isEmpty: value == null,
        decoration: InputDecoration(
          labelText: start ? 'Début' : 'Fin',
          errorText: _errors[key],
          errorMaxLines: 2,
          suffixIcon: value == null
              ? const Icon(Icons.event_outlined, size: 20)
              : IconButton(
                  tooltip: 'Effacer la date',
                  icon: const Icon(Icons.close, size: 18),
                  onPressed: () => setState(() {
                    if (start) {
                      _startsAt = null;
                    } else {
                      _endsAt = null;
                    }
                  }),
                ),
        ),
        child: Text(value == null ? '' : formatShortDate(value)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Nouveau code promo', style: headingStyle(fontSize: 21)),
            const SizedBox(height: 16),
            TextField(
              controller: _code,
              autofocus: true,
              maxLength: 40,
              textCapitalization: TextCapitalization.characters,
              autocorrect: false,
              enableSuggestions: false,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp('[A-Za-z0-9_-]')),
                _UpperCaseFormatter(),
              ],
              style: const TextStyle(fontWeight: FontWeight.w700, letterSpacing: 0.8),
              decoration: InputDecoration(
                labelText: 'Code *',
                hintText: 'Ex. : BIENVENUE10',
                counterText: '',
                errorText: _errors['code'],
              ),
            ),
            const SizedBox(height: 14),
            const Text('Type de réduction', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'percentage', icon: Icon(Icons.percent), label: Text('Pourcentage')),
                ButtonSegment(
                  value: 'fixed',
                  icon: Icon(Icons.payments_outlined),
                  label: Text('Montant fixe'),
                ),
              ],
              selected: {_type},
              onSelectionChanged: (selection) => setState(() => _type = selection.first),
            ),
            if (_errors['type'] != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(_errors['type']!, style: const TextStyle(color: danger, fontSize: 12)),
              ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextField(
                    controller: _value,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      labelText: 'Valeur (${_type == 'percentage' ? '%' : _unit}) *',
                      errorText: _errors['value'],
                      errorMaxLines: 3,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _minOrder,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      labelText: 'Minimum d’achat ($_unit)',
                      errorText: _errors['min_order_amount'],
                      errorMaxLines: 3,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: _dateField(start: true)),
                const SizedBox(width: 12),
                Expanded(child: _dateField(start: false)),
              ],
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _usageLimit,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: InputDecoration(
                labelText: 'Nombre d’utilisations max.',
                hintText: 'Illimité',
                helperText: 'Laissez vide ou 0 pour un usage illimité.',
                errorText: _errors['usage_limit'],
              ),
            ),
            const SizedBox(height: 6),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: const Text('Actif dès sa création'),
            ),
            if (_errors['status'] != null)
              Text(_errors['status']!, style: const TextStyle(color: danger, fontSize: 12)),
            const SizedBox(height: 12),
            LoadingButton(
              label: 'Créer le code promo',
              icon: Icons.local_offer_outlined,
              loading: _saving,
              onPressed: _submit,
            ),
            const SizedBox(height: 4),
            TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context),
              child: const Text('Annuler'),
            ),
          ],
        ),
      ),
    );
  }
}

class _UpperCaseFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) =>
      newValue.copyWith(text: newValue.text.toUpperCase());
}

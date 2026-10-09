import 'package:flutter/material.dart';

import '../../app/format.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';

/// Admin queue of products flagged by the anti-fraud agent, port of `pages/admin/moderation.vue`.
class ModerationScreen extends StatefulWidget {
  const ModerationScreen({super.key});

  @override
  State<ModerationScreen> createState() => _ModerationScreenState();
}

const _filters = [
  (ModerationStatus.pendingReview, 'À examiner'),
  (ModerationStatus.rejected, 'Refusés'),
  (ModerationStatus.approved, 'Approuvés'),
];

class _ModerationScreenState extends State<ModerationScreen> {
  ModerationStatus _filter = ModerationStatus.pendingReview;
  List<Product>? _products;
  int _page = 1;
  bool _hasMore = false;
  int _total = 0;
  bool _loading = false;
  bool _loadingMore = false;
  Object? _error;
  int _request = 0;
  final Set<int> _deciding = {};
  final Map<int, TextEditingController> _notes = {};
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    _load(rebuild: false);
  }

  @override
  void dispose() {
    for (final controller in _notes.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController _noteFor(Product product) =>
      _notes.putIfAbsent(product.id, TextEditingController.new);

  void _forgetNote(int productId) => _notes.remove(productId)?.dispose();

  /// Reloads the first page of the current filter.
  Future<void> _load({bool rebuild = true}) async {
    final request = ++_request;
    void start() {
      _loading = _products == null;
      _error = null;
    }

    rebuild ? setState(start) : start();
    try {
      final page = await context.api.getModerationQueue(status: _filter.value);
      if (!mounted || request != _request) return;
      final ids = page.items.map((product) => product.id).toSet();
      for (final id in _notes.keys.where((id) => !ids.contains(id)).toList()) {
        _forgetNote(id);
      }
      setState(() {
        _products = page.items;
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on Object catch (error) {
      if (!mounted || request != _request) return;
      if (_products == null) {
        setState(() => _error = error);
      } else {
        showToast(context, errorText(error), error: true);
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    final request = _request;
    setState(() => _loadingMore = true);
    try {
      final page = await context.api.getModerationQueue(status: _filter.value, page: _page + 1);
      if (!mounted || request != _request) return;
      setState(() {
        final known = (_products ?? const <Product>[]).map((product) => product.id).toSet();
        _products = [
          ...?_products,
          ...page.items.where((product) => !known.contains(product.id)),
        ];
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on Object catch (error) {
      if (!mounted) return;
      showToast(context, errorText(error), error: true);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _selectFilter(ModerationStatus filter) {
    if (filter == _filter) return;
    setState(() {
      _filter = filter;
      _products = null;
      _hasMore = false;
      _total = 0;
      _loadingMore = false;
    });
    _load();
  }

  Future<void> _decide(Product product, {required bool approve}) async {
    if (_deciding.contains(product.id)) return;
    FocusScope.of(context).unfocus();
    setState(() => _deciding.add(product.id));
    try {
      final updated = await context.api.decideModeration(
        product.id,
        approve: approve,
        note: _noteFor(product).text,
      );
      if (!mounted) return;
      showToast(
        context,
        approve ? '« ${updated.name} » approuvé.' : '« ${updated.name} » refusé.',
      );
      setState(() {
        final products = _products ?? const <Product>[];
        if (updated.moderation.status == _filter) {
          _products = [for (final item in products) item.id == updated.id ? updated : item];
        } else {
          _products = products.where((item) => item.id != updated.id).toList();
          _total = (_total - 1).clamp(0, _total);
          _forgetNote(updated.id);
        }
      });
    } on Object catch (error) {
      if (!mounted) return;
      showToast(
        context,
        errorText(error, 'La décision n’a pas pu être enregistrée.'),
        error: true,
      );
    } finally {
      if (mounted) setState(() => _deciding.remove(product.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Modération des produits')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          slivers: [
            SliverToBoxAdapter(child: _buildIntro()),
            ..._buildBody(),
            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }

  Widget _buildIntro() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.shield_outlined, color: forest, size: 20),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Produits signalés par l’agent IA anti-fraude, en attente d’une décision '
                  'humaine.',
                  style: TextStyle(color: muted, height: 1.4),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (status, label) in _filters)
                ChoiceChip(
                  label: Text(
                    _filter == status && _products != null ? '$label ($_total)' : label,
                  ),
                  selected: _filter == status,
                  showCheckmark: false,
                  labelStyle: TextStyle(
                    color: _filter == status ? ivory : ink,
                    fontWeight: FontWeight.w600,
                  ),
                  onSelected: (_) => _selectFilter(status),
                ),
            ],
          ),
        ],
      ),
    );
  }

  List<Widget> _buildBody() {
    final products = _products;
    if (_loading && products == null) {
      return const [
        SliverFillRemaining(
          hasScrollBody: false,
          child: LoadingView(text: 'Chargement de la file de modération…'),
        ),
      ];
    }
    if (_error != null && products == null) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: MessageState(
            icon: Icons.cloud_off_outlined,
            title: 'File de modération indisponible',
            message: errorText(_error!),
            onRetry: _load,
          ),
        ),
      ];
    }
    if (products == null || products.isEmpty) {
      return const [
        SliverFillRemaining(
          hasScrollBody: false,
          child: MessageState(
            icon: Icons.done_all,
            title: 'Rien à signaler',
            message: 'Aucun produit dans cette file. L’agent IA veille.',
          ),
        ),
      ];
    }
    return [
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverList.separated(
          itemCount: products.length,
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final product = products[index];
            return _ModerationCard(
              key: ValueKey(product.id),
              product: product,
              note: _noteFor(product),
              deciding: _deciding.contains(product.id),
              onApprove: () => _decide(product, approve: true),
              onReject: () => _decide(product, approve: false),
            );
          },
        ),
      ),
      if (_hasMore)
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Center(
              child: OutlinedButton.icon(
                onPressed: _loadingMore ? null : _loadMore,
                icon: _loadingMore
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more),
                label: const Text('Charger plus'),
              ),
            ),
          ),
        ),
    ];
  }
}

class _ModerationCard extends StatelessWidget {
  const _ModerationCard({
    required this.product,
    required this.note,
    required this.deciding,
    required this.onApprove,
    required this.onReject,
    super.key,
  });

  final Product product;
  final TextEditingController note;
  final bool deciding;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  static Color _riskColor(int score) => score >= 70
      ? danger
      : score >= 30
      ? warning
      : success;

  @override
  Widget build(BuildContext context) {
    final moderation = product.moderation;
    final score = moderation.riskScore.clamp(0, 100);
    final description = product.description;
    final excerpt = description == null
        ? null
        : description.length > 220
        ? '${description.substring(0, 220)}…'
        : description;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: ProductImage(url: product.imageUrl, width: 84, height: 84),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        product.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: headingStyle(fontSize: 17),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.storefront_outlined, size: 14, color: muted),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              product.shopName.isEmpty
                                  ? 'Boutique #${product.shopId}'
                                  : product.shopName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: muted, fontSize: 12.5),
                            ),
                          ),
                        ],
                      ),
                      if (product.createdAt != null)
                        Text(
                          formatDate(product.createdAt),
                          style: const TextStyle(color: muted, fontSize: 12),
                        ),
                      const SizedBox(height: 6),
                      PriceTag(
                        amount: product.price,
                        currency: product.currency,
                        compareAt: product.compareAtPrice,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerLeft,
              child: ModerationBadge(moderation, showScore: true),
            ),
            if (excerpt != null) ...[
              const SizedBox(height: 10),
              Text(excerpt, style: const TextStyle(color: muted, fontSize: 13, height: 1.4)),
            ],
            const Divider(height: 26),
            Row(
              children: [
                const Expanded(
                  child: Text('Score de risque', style: TextStyle(fontWeight: FontWeight.w700)),
                ),
                Text(
                  '$score/100 · ${moderation.engine == 'claude' ? 'IA avancée' : 'IA locale'}',
                  style: const TextStyle(fontSize: 12.5, color: muted),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Semantics(
              label: 'Score de risque de ${product.name}',
              value: '$score sur 100',
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: score / 100,
                  minHeight: 10,
                  backgroundColor: tertiary,
                  color: _riskColor(score),
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (moderation.reasons.isEmpty)
              const Text('Aucun motif relevé.', style: TextStyle(color: muted, fontSize: 13))
            else
              for (final reason in moderation.reasons)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 6, right: 8),
                        child: Container(
                          width: 5,
                          height: 5,
                          decoration: BoxDecoration(
                            color: _riskColor(score),
                            shape: BoxShape.circle,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Text(reason, style: const TextStyle(fontSize: 13, height: 1.4)),
                      ),
                    ],
                  ),
                ),
            if (moderation.note case final existing?) ...[
              const SizedBox(height: 8),
              NoticeBanner(
                message: 'Note : $existing',
                tone: Tone.info,
                icon: Icons.sticky_note_2_outlined,
              ),
            ],
            const SizedBox(height: 14),
            TextField(
              controller: note,
              minLines: 2,
              maxLines: 4,
              enabled: !deciding,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Note pour le marchand (optionnel)',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: deciding ? null : onApprove,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: success,
                      foregroundColor: Colors.white,
                      disabledBackgroundColor: success.withValues(alpha: 0.4),
                    ),
                    icon: deciding
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.check),
                    label: const Text('Approuver'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: deciding ? null : onReject,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: danger,
                      minimumSize: const Size(48, 50),
                      side: BorderSide(color: danger.withValues(alpha: 0.5)),
                    ),
                    icon: const Icon(Icons.close),
                    label: const Text('Refuser'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

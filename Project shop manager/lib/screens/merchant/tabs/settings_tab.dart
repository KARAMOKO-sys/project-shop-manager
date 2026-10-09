import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/format.dart';
import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';
import '../product_form_screen.dart' show askImageSource, pickAndUploadShopImage;

/// General shop settings, mirroring `components/dashboard/ShopSettings.vue`.
class SettingsTab extends StatefulWidget {
  const SettingsTab({required this.shop, required this.onShopUpdated, required this.onDeleted, super.key});

  final Shop shop;
  final ValueChanged<Shop> onShopUpdated;
  final VoidCallback onDeleted;

  @override
  State<SettingsTab> createState() => _SettingsTabState();
}

class _SettingsTabState extends State<SettingsTab> with AutomaticKeepAliveClientMixin {
  late final _name = TextEditingController(text: widget.shop.name);
  late final _tagline = TextEditingController(text: widget.shop.tagline ?? '');
  late final _description = TextEditingController(text: widget.shop.description ?? '');
  late String _currency = currencies.containsKey(widget.shop.currency) ? widget.shop.currency : 'XOF';
  late bool _active = widget.shop.isActive;
  late String? _logoUrl = widget.shop.logoUrl;

  Map<String, String> _errors = const {};
  bool _saving = false;
  bool _savingLogo = false;
  bool _deleting = false;
  String? _logoError;

  @override
  bool get wantKeepAlive => true;

  @override
  void dispose() {
    _name.dispose();
    _tagline.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _uploadLogo() async {
    final source = await askImageSource(context);
    if (source == null || !mounted) return;
    setState(() {
      _savingLogo = true;
      _logoError = null;
    });
    try {
      final url = await pickAndUploadShopImage(context, shopId: widget.shop.id, source: source);
      if (!mounted) return;
      if (url == null) {
        setState(() => _savingLogo = false);
        return;
      }
      await _saveLogo(url);
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _logoError = errorText(error, 'Le téléversement a échoué.');
        _savingLogo = false;
      });
    }
  }

  Future<void> _saveLogo(String? url) async {
    setState(() {
      _savingLogo = true;
      _logoError = null;
    });
    try {
      final updated = await context.api.updateShop(widget.shop.id, {'logo_url': url});
      if (!mounted) return;
      setState(() => _logoUrl = updated.logoUrl);
      widget.onShopUpdated(updated);
      showToast(context, url != null ? 'Logo mis à jour.' : 'Logo retiré.');
    } on Object catch (error) {
      if (!mounted) return;
      final message = error is ApiException ? error.fieldError('logo_url') : null;
      showToast(context, message ?? errorText(error, 'Le logo n’a pas pu être enregistré.'), error: true);
    } finally {
      if (mounted) setState(() => _savingLogo = false);
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => _errors = {'name': 'Le nom de la boutique est obligatoire.'});
      return;
    }
    setState(() {
      _saving = true;
      _errors = const {};
    });
    final tagline = _tagline.text.trim();
    final description = _description.text.trim();
    try {
      final updated = await context.api.updateShop(widget.shop.id, {
        'name': name,
        'tagline': tagline.isEmpty ? null : tagline,
        'description': description.isEmpty ? null : description,
        'currency': _currency,
        'status': _active ? 'active' : 'inactive',
      });
      if (!mounted) return;
      widget.onShopUpdated(updated);
      showToast(context, 'Paramètres enregistrés.');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _errors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) showToast(context, error.message, error: true);
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete() async {
    final confirmed = await confirmAction(
      context,
      title: 'Supprimer la boutique ?',
      message: 'Supprimer définitivement la boutique « ${widget.shop.name} » et tous ses produits ?',
      confirmLabel: 'Supprimer la boutique',
    );
    if (!confirmed || !mounted) return;
    setState(() => _deleting = true);
    try {
      await context.api.deleteShop(widget.shop.id);
      if (!mounted) return;
      showToast(context, 'Boutique supprimée.');
      widget.onDeleted();
    } on Object catch (error) {
      if (!mounted) return;
      showToast(context, errorText(error, 'La suppression a échoué.'), error: true);
      setState(() => _deleting = false);
    }
  }

  Future<void> _copyUrl() async {
    await Clipboard.setData(ClipboardData(text: widget.shop.url));
    if (mounted) showToast(context, 'Lien de la boutique copié.');
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          _logoSection(),
          const SizedBox(height: 16),
          if (widget.shop.url.isNotEmpty) ...[_urlSection(), const SizedBox(height: 16)],
          _generalSection(),
          const SizedBox(height: 16),
          _dangerSection(),
        ],
      ),
    );
  }

  Widget _logoSection() {
    return FormSection(
      title: 'Logo',
      children: [
        Row(
          children: [
            Stack(
              alignment: Alignment.center,
              children: [
                ShopLogo(
                  name: widget.shop.name,
                  logoUrl: _logoUrl,
                  color: widget.shop.theme.primary,
                  size: 84,
                ),
                if (_savingLogo)
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.7),
                      borderRadius: BorderRadius.circular(84 * 0.3),
                    ),
                    alignment: Alignment.center,
                    child: const SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(strokeWidth: 2.5),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  OutlinedButton.icon(
                    onPressed: _savingLogo ? null : _uploadLogo,
                    icon: const Icon(Icons.upload_outlined, size: 18),
                    label: const Text('Téléverser un logo'),
                  ),
                  if (_logoUrl != null) ...[
                    const SizedBox(height: 6),
                    TextButton.icon(
                      onPressed: _savingLogo ? null : () => _saveLogo(null),
                      style: TextButton.styleFrom(foregroundColor: danger),
                      icon: const Icon(Icons.close, size: 18),
                      label: const Text('Retirer le logo'),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        const Text(
          'Format carré conseillé. JPEG, PNG ou WebP — 4 Mo maximum.',
          style: TextStyle(color: muted, fontSize: 12.5),
        ),
        if (_logoError != null) ...[const SizedBox(height: 10), NoticeBanner(message: _logoError!)],
      ],
    );
  }

  Widget _urlSection() {
    return FormSection(
      title: 'Adresse de votre boutique',
      subtitle: 'Partagez ce lien sur WhatsApp et les réseaux sociaux.',
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(12, 4, 4, 4),
          decoration: BoxDecoration(
            color: tertiary,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: border),
          ),
          child: Row(
            children: [
              const Icon(Icons.link, size: 18, color: goldInk),
              const SizedBox(width: 8),
              Expanded(
                child: SelectableText(
                  widget.shop.url,
                  maxLines: 2,
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
              IconButton(
                tooltip: 'Copier le lien',
                onPressed: _copyUrl,
                icon: const Icon(Icons.copy_rounded, size: 19, color: forest),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _generalSection() {
    return FormSection(
      title: 'Informations générales',
      children: [
        TextField(
          controller: _name,
          maxLength: 120,
          textCapitalization: TextCapitalization.sentences,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: 'Nom de la boutique',
            counterText: '',
            errorText: _errors['name'],
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _currency,
          isExpanded: true,
          decoration: InputDecoration(labelText: 'Devise', errorText: _errors['currency']),
          items: [
            for (final entry in currencies.entries)
              DropdownMenuItem(
                value: entry.key,
                child: Text(entry.value, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (value) {
            if (value != null) setState(() => _currency = value);
          },
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _tagline,
          maxLength: 160,
          textCapitalization: TextCapitalization.sentences,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(labelText: 'Slogan', counterText: '', errorText: _errors['tagline']),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _description,
          minLines: 4,
          maxLines: 8,
          maxLength: 2000,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            labelText: 'Description',
            alignLabelWithHint: true,
            errorText: _errors['description'],
          ),
        ),
        const SizedBox(height: 4),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _active,
          onChanged: (value) => setState(() => _active = value),
          title: const Text('Boutique en ligne', style: TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Text(
            _active ? 'Votre boutique est visible par vos clients.' : 'Votre boutique est masquée du public.',
            style: const TextStyle(color: muted, fontSize: 12.5),
          ),
        ),
        if (_errors['status'] != null)
          Text(_errors['status']!, style: const TextStyle(color: danger, fontSize: 12)),
        const SizedBox(height: 12),
        LoadingButton(label: 'Enregistrer', icon: Icons.check, loading: _saving, onPressed: _save),
      ],
    );
  }

  Widget _dangerSection() {
    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: danger.withValues(alpha: 0.3)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Zone de danger', style: headingStyle(fontSize: 18, color: danger)),
            const SizedBox(height: 6),
            const Text(
              'La suppression est définitive : produits, commandes et paramètres seront perdus.',
              style: TextStyle(color: muted, height: 1.4),
            ),
            const SizedBox(height: 14),
            OutlinedButton.icon(
              onPressed: _deleting ? null : _delete,
              style: OutlinedButton.styleFrom(
                foregroundColor: danger,
                side: BorderSide(color: danger.withValues(alpha: 0.5)),
              ),
              icon: _deleting
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2, color: danger),
                    )
                  : const Icon(Icons.delete_outline),
              label: const Text('Supprimer la boutique'),
            ),
          ],
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';

import '../../../app/scope.dart';
import '../../../app/theme.dart';
import '../../../models/models.dart';
import '../../../services/api_service.dart';
import '../../../widgets/common.dart';
import '../../../widgets/theme_preview.dart';

/// Storefront look & feel, mirroring `components/dashboard/ShopAppearance.vue`.
class AppearanceTab extends StatefulWidget {
  const AppearanceTab({required this.shop, required this.onShopUpdated, super.key});

  final Shop shop;
  final ValueChanged<Shop> onShopUpdated;

  @override
  State<AppearanceTab> createState() => _AppearanceTabState();
}

class _AppearanceTabState extends State<AppearanceTab> with AutomaticKeepAliveClientMixin {
  static const _maxRadius = 24.0;

  late String _template = widget.shop.template;
  late ShopTheme _theme = widget.shop.theme;
  List<ShopTemplate>? _templates;
  bool _loadingTemplates = true;
  bool _saving = false;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadTemplates());
  }

  Future<void> _loadTemplates() async {
    if (!mounted) return;
    setState(() => _loadingTemplates = true);
    try {
      final templates = await context.api.getTemplates();
      if (!mounted) return;
      setState(() => _templates = templates);
    } on Object {
      if (mounted) setState(() => _templates = null);
    } finally {
      if (mounted) setState(() => _loadingTemplates = false);
    }
  }

  void _applyTemplate(ShopTemplate template) => setState(() {
    _template = template.key;
    _theme = template.theme;
  });

  Future<void> _pickColor(String label, Color current, ValueChanged<Color> apply) async {
    final color = await showDialog<Color>(
      context: context,
      builder: (_) => _ColorPickerDialog(title: 'Couleur $label'.trim(), initial: current),
    );
    if (color != null && mounted) setState(() => apply(color));
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final updated = await context.api.updateShop(widget.shop.id, {
        'template': _template,
        'theme': _theme.toJson(),
      });
      if (!mounted) return;
      widget.onShopUpdated(updated);
      showToast(context, 'Apparence enregistrée. Votre boutique a un nouveau look !');
    } on Object catch (error) {
      if (mounted) {
        showToast(context, errorText(error, 'L’apparence n’a pas pu être enregistrée.'), error: true);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final templateName =
        _templates
            ?.where((template) => template.key == _template)
            .map((template) => template.name)
            .firstOrNull ??
        _template;
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          decoration: const BoxDecoration(
            color: ivory,
            border: Border(bottom: BorderSide(color: border)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Icon(Icons.visibility_outlined, size: 16, color: goldInk),
                  const SizedBox(width: 6),
                  const Text(
                    'APERÇU EN DIRECT',
                    style: TextStyle(
                      color: goldInk,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const Spacer(),
                  Flexible(
                    child: Text(
                      '${_theme.format.label} · $templateName',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: muted, fontSize: 12),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              ThemePreview(
                theme: _theme,
                title: widget.shop.name,
                subtitle: widget.shop.tagline,
                height: 124,
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            children: [
              _templateSection(),
              const SizedBox(height: 16),
              _formatSection(),
              const SizedBox(height: 16),
              _colorSection(),
              const SizedBox(height: 16),
              _styleSection(),
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            decoration: const BoxDecoration(
              color: ivory,
              border: Border(top: BorderSide(color: border)),
            ),
            child: LoadingButton(
              label: 'Enregistrer l’apparence',
              icon: Icons.check,
              loading: _saving,
              onPressed: _save,
            ),
          ),
        ),
      ],
    );
  }

  Widget _templateSection() {
    final templates = _templates;
    return FormSection(
      title: 'Modèle',
      subtitle: 'Appliquer un modèle remplace le format, les couleurs, la police et le style.',
      children: [
        if (_loadingTemplates && templates == null)
          const SizedBox(height: 120, child: LoadingView(text: 'Chargement des modèles…'))
        else if (templates == null || templates.isEmpty)
          Row(
            children: [
              const Expanded(
                child: Text('Modèles indisponibles pour le moment.', style: TextStyle(color: muted)),
              ),
              TextButton.icon(
                onPressed: _loadTemplates,
                icon: const Icon(Icons.refresh, size: 18),
                label: const Text('Réessayer'),
              ),
            ],
          )
        else
          SizedBox(
            height: 340,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: templates.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final template = templates[index];
                return SizedBox(
                  width: 240,
                  child: TemplateCard(
                    template: template,
                    selected: template.key == _template,
                    onTap: () => _applyTemplate(template),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }

  Widget _formatSection() {
    return FormSection(
      title: 'Format de la boutique',
      subtitle: 'La structure de votre page d’accueil, indépendamment des couleurs.',
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            const spacing = 10.0;
            final width = (constraints.maxWidth - spacing) / 2;
            return Wrap(
              spacing: spacing,
              runSpacing: spacing,
              children: [
                for (final format in ShopFormat.values)
                  SizedBox(
                    width: width,
                    child: _FormatOption(
                      format: format,
                      selected: _theme.format == format,
                      onTap: () => setState(() => _theme = _theme.copyWith(format: format)),
                    ),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _colorSection() {
    final fields = [
      (
        label: 'Principale',
        color: _theme.primary,
        apply: (Color color) => _theme = _theme.copyWith(primary: color),
      ),
      (
        label: 'Secondaire',
        color: _theme.secondary,
        apply: (Color color) => _theme = _theme.copyWith(secondary: color),
      ),
      (
        label: 'Fond',
        color: _theme.background,
        apply: (Color color) => _theme = _theme.copyWith(background: color),
      ),
      (
        label: 'Cartes',
        color: _theme.surface,
        apply: (Color color) => _theme = _theme.copyWith(surface: color),
      ),
      (label: 'Texte', color: _theme.text, apply: (Color color) => _theme = _theme.copyWith(text: color)),
    ];
    return FormSection(
      title: 'Couleurs',
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            const spacing = 10.0;
            final columns = constraints.maxWidth >= 460 ? 5 : 3;
            final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;
            return Wrap(
              spacing: spacing,
              runSpacing: spacing,
              children: [
                for (final field in fields)
                  SizedBox(
                    width: width,
                    child: _ColorField(
                      label: field.label,
                      color: field.color,
                      onTap: () => _pickColor(field.label.toLowerCase(), field.color, field.apply),
                    ),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _styleSection() {
    final radius = _theme.radius.clamp(0, _maxRadius).toDouble();
    final font = themeFonts.contains(_theme.font) ? _theme.font : themeFonts.first;
    return FormSection(
      title: 'Style',
      children: [
        if (_theme.format != ShopFormat.classic) ...[
          const NoticeBanner(
            tone: Tone.info,
            message:
                'La bannière d’accueil et l’affichage des produits s’appliquent surtout '
                'au format Classique.',
          ),
          const SizedBox(height: 14),
        ],
        DropdownButtonFormField<String>(
          key: ValueKey('font-$font'),
          initialValue: font,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Police'),
          items: [for (final item in themeFonts) DropdownMenuItem(value: item, child: Text(item))],
          onChanged: (value) {
            if (value != null) setState(() => _theme = _theme.copyWith(font: value));
          },
        ),
        const SizedBox(height: 16),
        Text('Arrondi des coins : ${radius.round()} px', style: const TextStyle(fontWeight: FontWeight.w600)),
        Slider(
          value: radius,
          max: _maxRadius,
          divisions: 12,
          label: '${radius.round()} px',
          activeColor: forest,
          inactiveColor: forestSoft,
          onChanged: (value) => setState(() => _theme = _theme.copyWith(radius: value)),
        ),
        const SizedBox(height: 4),
        const Text('Bannière d’accueil', style: TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        _choices(
          options: heroStyles,
          selected: _theme.heroStyle,
          onSelected: (value) => _theme = _theme.copyWith(heroStyle: value),
        ),
        const SizedBox(height: 16),
        const Text('Affichage des produits', style: TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        _choices(
          options: productLayouts,
          selected: _theme.layout,
          onSelected: (value) => _theme = _theme.copyWith(layout: value),
        ),
      ],
    );
  }

  Widget _choices({
    required Map<String, String> options,
    required String selected,
    required ValueChanged<String> onSelected,
  }) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final entry in options.entries)
          ChoiceChip(
            label: Text(entry.value),
            selected: selected == entry.key,
            labelStyle: TextStyle(color: selected == entry.key ? ivory : ink, fontWeight: FontWeight.w600),
            onSelected: (_) => setState(() => onSelected(entry.key)),
          ),
      ],
    );
  }
}

class _FormatOption extends StatelessWidget {
  const _FormatOption({required this.format, required this.selected, required this.onTap});

  final ShopFormat format;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? goldSoft : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: selected ? gold : border, width: selected ? 2 : 1),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: SizedBox(
            height: 132,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(format.icon, size: 20, color: selected ? goldInk : forest),
                      const Spacer(),
                      if (selected) const Icon(Icons.check_circle, size: 18, color: gold),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(format.label, style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Expanded(
                    child: Text(
                      format.description,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: muted, fontSize: 12, height: 1.3),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ColorField extends StatelessWidget {
  const _ColorField({required this.label, required this.color, required this.onTap});

  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Couleur $label : ${colorToHex(color)}',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            Container(
              height: 44,
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: border, width: 1.5),
              ),
              alignment: Alignment.bottomRight,
              padding: const EdgeInsets.all(4),
              child: Icon(Icons.edit, size: 14, color: contrastingText(color).withValues(alpha: 0.7)),
            ),
            const SizedBox(height: 4),
            Text(
              colorToHex(color),
              style: const TextStyle(fontSize: 11.5, color: muted, fontFamily: 'monospace'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Simple colour picker: a curated palette plus a free hexadecimal field.
class _ColorPickerDialog extends StatefulWidget {
  const _ColorPickerDialog({required this.title, required this.initial});

  final String title;
  final Color initial;

  @override
  State<_ColorPickerDialog> createState() => _ColorPickerDialogState();
}

class _ColorPickerDialogState extends State<_ColorPickerDialog> {
  static const _palette = [
    Color(0xFF0F3D2E),
    Color(0xFF1F6F8B),
    Color(0xFF1E3A8A),
    Color(0xFF4C1D95),
    Color(0xFF7D1A15),
    Color(0xFFB3261E),
    Color(0xFFC2410C),
    Color(0xFFC8963E),
    Color(0xFFE0B252),
    Color(0xFF2E7D4F),
    Color(0xFF65A30D),
    Color(0xFF0D9488),
    Color(0xFFDB2777),
    Color(0xFF9D174D),
    Color(0xFF6D4C41),
    Color(0xFF3E2723),
    Color(0xFF1C1B17),
    Color(0xFF374151),
    Color(0xFF5E5A50),
    Color(0xFF9CA3AF),
    Color(0xFFE8E1D3),
    Color(0xFFF4EAD7),
    Color(0xFFFAF7F0),
    Color(0xFFFFFFFF),
  ];

  late Color _color = widget.initial;
  late final _hex = TextEditingController(text: colorToHex(widget.initial));
  String? _error;

  @override
  void dispose() {
    _hex.dispose();
    super.dispose();
  }

  void _select(Color color) => setState(() {
    _color = color;
    _error = null;
    _hex.text = colorToHex(color);
  });

  void _onHexChanged(String value) {
    final parsed = parseHexColor(value);
    setState(() {
      if (parsed != null) {
        _color = parsed;
        _error = null;
      } else {
        _error = value.trim().isEmpty ? null : 'Couleur invalide (ex. : #0F3D2E).';
      }
    });
  }

  void _confirm() {
    final parsed = parseHexColor(_hex.text);
    if (parsed == null) {
      setState(() => _error = 'Couleur invalide (ex. : #0F3D2E).');
      return;
    }
    Navigator.pop(context, parsed);
  }

  @override
  Widget build(BuildContext context) {
    final selectedHex = colorToHex(_color);
    return AlertDialog(
      title: Text(widget.title, style: headingStyle(fontSize: 20)),
      contentPadding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      content: SizedBox(
        width: 320,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                height: 54,
                decoration: BoxDecoration(
                  color: _color,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: border),
                ),
                alignment: Alignment.center,
                child: Text(
                  selectedHex,
                  style: TextStyle(
                    color: contrastingText(_color),
                    fontWeight: FontWeight.w700,
                    fontFamily: 'monospace',
                  ),
                ),
              ),
              const SizedBox(height: 14),
              GridView.count(
                crossAxisCount: 6,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 8,
                crossAxisSpacing: 8,
                children: [
                  for (final swatch in _palette)
                    Semantics(
                      button: true,
                      selected: colorToHex(swatch) == selectedHex,
                      label: colorToHex(swatch),
                      child: InkWell(
                        onTap: () => _select(swatch),
                        customBorder: const CircleBorder(),
                        child: Container(
                          decoration: BoxDecoration(
                            color: swatch,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: colorToHex(swatch) == selectedHex ? gold : border,
                              width: colorToHex(swatch) == selectedHex ? 3 : 1,
                            ),
                          ),
                          child: colorToHex(swatch) == selectedHex
                              ? Icon(Icons.check, size: 18, color: contrastingText(swatch))
                              : null,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _hex,
                maxLength: 7,
                autocorrect: false,
                enableSuggestions: false,
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(
                  labelText: 'Code hexadécimal',
                  hintText: '#0F3D2E',
                  counterText: '',
                  errorText: _error,
                  prefixIcon: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Container(
                      width: 18,
                      height: 18,
                      decoration: BoxDecoration(
                        color: _color,
                        borderRadius: BorderRadius.circular(5),
                        border: Border.all(color: border),
                      ),
                    ),
                  ),
                ),
                onChanged: _onHexChanged,
                onSubmitted: (_) => _confirm(),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
        ElevatedButton(onPressed: _confirm, child: const Text('Appliquer')),
      ],
    );
  }
}

import 'package:flutter/material.dart';

import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../../widgets/theme_preview.dart';
import '../auth/login_screen.dart';
import 'shop_dashboard_screen.dart';

/// Three-step shop wizard, port of the web `pages/creer-ma-boutique.vue`.
///
/// [templateKey] pre-selects a template picked in the gallery (web `?modele=`).
class CreateShopScreen extends StatefulWidget {
  const CreateShopScreen({this.templateKey, super.key});

  final String? templateKey;

  @override
  State<CreateShopScreen> createState() => _CreateShopScreenState();
}

const _stepLabels = ['Votre activité', 'Votre boutique', 'Votre style'];
const _detailsFields = ['name', 'tagline', 'description', 'sector', 'categories'];
const _maxCategories = 12;

/// Ready-made primary colors offered next to the template's own color.
const _presetColors = [
  Color(0xFF0F3D2E),
  Color(0xFFB5532A),
  Color(0xFF7A1F3D),
  Color(0xFF2E3A8C),
  Color(0xFF1F6F8B),
  Color(0xFF1E7B4F),
  Color(0xFFC8963E),
  Color(0xFF1C1B17),
];

class _CreateShopScreenState extends State<CreateShopScreen> {
  final _prompt = TextEditingController();
  final _name = TextEditingController();
  final _tagline = TextEditingController();
  final _description = TextEditingController();
  final _sector = TextEditingController();
  final _categoryDraft = TextEditingController();
  final _categoryFocus = FocusNode();

  int _step = 1;
  List<String> _categories = [];
  String? _template;
  String? _recommendedTemplate;
  Color? _primaryOverride;
  bool _pickedFromGallery = false;
  ShopFormat? _formatFilter;

  List<ShopTemplate>? _templates;
  Object? _templatesError;
  bool _templatesLoading = false;

  bool _aiLoading = false;
  String? _aiError;
  bool _submitting = false;
  Map<String, String> _errors = const {};
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    _loadTemplates(rebuild: false);
  }

  @override
  void dispose() {
    _prompt.dispose();
    _name.dispose();
    _tagline.dispose();
    _description.dispose();
    _sector.dispose();
    _categoryDraft.dispose();
    _categoryFocus.dispose();
    super.dispose();
  }

  ShopTemplate? get _selectedTemplate {
    for (final template in _templates ?? const <ShopTemplate>[]) {
      if (template.key == _template) return template;
    }
    return null;
  }

  ShopTheme get _previewTheme {
    final theme = _selectedTemplate?.theme ?? const ShopTheme();
    return _primaryOverride == null ? theme : theme.copyWith(primary: _primaryOverride);
  }

  List<ShopTemplate> get _filteredTemplates => (_templates ?? const <ShopTemplate>[])
      .where((template) => _formatFilter == null || template.theme.format == _formatFilter)
      .toList();

  /// First message of [field] or of a nested key (`categories.0`).
  String? _error(String field) {
    if (_errors[field] case final message?) return message;
    for (final entry in _errors.entries) {
      if (entry.key.startsWith('$field.')) return entry.value;
    }
    return null;
  }

  void _clearError(String field) {
    if (_error(field) == null) return;
    setState(() {
      _errors = Map.of(_errors)
        ..removeWhere((key, _) => key == field || key.startsWith('$field.'));
    });
  }

  // ---------- Data ----------

  Future<void> _loadTemplates({bool rebuild = true}) async {
    void start() {
      _templatesLoading = true;
      _templatesError = null;
    }

    rebuild ? setState(start) : start();
    try {
      final templates = await context.api.getTemplates();
      if (!mounted) return;
      setState(() {
        _templates = templates;
        if (templates.isNotEmpty && _template == null) {
          ShopTemplate? requested;
          for (final template in templates) {
            if (template.key == widget.templateKey) requested = template;
          }
          _template = requested?.key ?? templates.first.key;
          _pickedFromGallery = requested != null;
        }
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() => _templatesError = error);
    } finally {
      if (mounted) setState(() => _templatesLoading = false);
    }
  }

  Future<void> _suggestWithAi() async {
    if (_aiLoading) return;
    if (_prompt.text.trim().length < 5) {
      setState(() {
        _aiError =
            'Décrivez votre activité en quelques mots (ex. « Je vends des jus naturels à Dakar »).';
      });
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _aiLoading = true;
      _aiError = null;
    });
    try {
      final data = await context.api.suggestShopSetup(_prompt.text.trim());
      if (!mounted) return;
      setState(() {
        _name.text = data.name;
        _tagline.text = data.tagline;
        _description.text = data.description;
        _sector.text = data.sector;
        _categories = data.categories.take(_maxCategories).toList();
        if (data.template.isNotEmpty) {
          _recommendedTemplate = data.template;
          final known = _templates?.any((template) => template.key == data.template) ?? true;
          // A template explicitly picked in the gallery wins over the AI suggestion.
          if (!_pickedFromGallery && known) {
            _template = data.template;
            _primaryOverride = null;
          }
        }
        _errors = const {};
        _step = 2;
      });
      showToast(context, 'L’IA a préparé votre boutique. Vérifiez et ajustez si besoin.');
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _aiError = errorText(
          error,
          'L’IA n’a pas pu répondre. Vous pouvez remplir les informations vous-même.',
        );
      });
    } finally {
      if (mounted) setState(() => _aiLoading = false);
    }
  }

  void _goToStep(int target) {
    FocusScope.of(context).unfocus();
    if (target == 3) {
      _commitCategory();
      if (_name.text.trim().isEmpty) {
        setState(() => _errors = {..._errors, 'name': 'Donnez un nom à votre boutique.'});
        return;
      }
    }
    setState(() => _step = target);
  }

  void _commitCategory() {
    final value = _categoryDraft.text.replaceAll(',', '').trim();
    _categoryDraft.clear();
    if (value.isEmpty || _categories.length >= _maxCategories) return;
    final exists = _categories.any((tag) => tag.toLowerCase() == value.toLowerCase());
    if (exists) return;
    setState(() => _categories = [..._categories, value]);
    _clearError('categories');
  }

  void _removeCategory(String category) {
    setState(() => _categories = _categories.where((tag) => tag != category).toList());
  }

  void _chooseTemplate(ShopTemplate template) {
    setState(() {
      _template = template.key;
      _pickedFromGallery = true;
      _primaryOverride = null;
    });
    _clearError('template');
  }

  void _choosePrimary(Color color) {
    final templatePrimary = _selectedTemplate?.theme.primary;
    final isTemplateColor =
        templatePrimary != null && colorToHex(templatePrimary) == colorToHex(color);
    setState(() => _primaryOverride = isTemplateColor ? null : color);
  }

  String? _optional(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  Future<void> _submit() async {
    final template = _selectedTemplate;
    if (_submitting || template == null) return;
    setState(() {
      _submitting = true;
      _errors = const {};
    });
    final api = context.api;
    try {
      var shop = await api.createShop({
        'name': _name.text.trim(),
        'template': template.key,
        'tagline': ?_optional(_tagline),
        'description': ?_optional(_description),
        'sector': ?_optional(_sector),
        'categories': _categories,
        'currency': 'XOF',
      });

      var colorSaved = true;
      final override = _primaryOverride;
      if (override != null && colorToHex(override) != colorToHex(template.theme.primary)) {
        try {
          shop = await api.updateShop(shop.id, {
            'theme': {'primary': colorToHex(override)},
          });
        } on Object {
          colorSaved = false;
        }
      }
      if (!mounted) return;

      if (colorSaved) {
        showToast(context, 'Félicitations ! « ${shop.name} » est en ligne.');
      } else {
        showToast(
          context,
          'Boutique créée, mais la couleur personnalisée n’a pas pu être enregistrée.',
          error: true,
        );
      }
      final created = shop;
      await Navigator.pushReplacement(
        context,
        MaterialPageRoute<void>(
          builder: (_) => ShopDashboardScreen(shopId: created.id, initialShop: created),
        ),
      );
    } on Object catch (error) {
      if (!mounted) return;
      final fields = error is ApiException ? error.fieldErrors : const <String, String>{};
      final touchesDetails = fields.keys.any(
        (field) => _detailsFields.any((name) => field.startsWith(name)),
      );
      setState(() {
        _errors = fields;
        if (touchesDetails) _step = 2;
      });
      if (fields.isEmpty) {
        showToast(context, errorText(error, 'La création de la boutique a échoué.'), error: true);
      } else if (!touchesDetails && _error('template') == null) {
        showToast(context, fields.values.first, error: true);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  // ---------- UI ----------

  @override
  Widget build(BuildContext context) {
    final session = context.session;
    return ListenableBuilder(
      listenable: session,
      builder: (context, _) {
        if (!session.isAuthenticated) return _buildSignedOut();
        return PopScope(
          canPop: _step == 1 && !_submitting,
          onPopInvokedWithResult: (didPop, _) {
            if (didPop || _submitting) return;
            setState(() => _step--);
          },
          child: Scaffold(
            appBar: AppBar(title: const Text('Créer ma boutique')),
            body: SafeArea(
              top: false,
              child: ListView(
                keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                children: [
                  _buildHeader(),
                  const SizedBox(height: 18),
                  ...switch (_step) {
                    1 => _buildActivityStep(),
                    2 => _buildDetailsStep(),
                    _ => _buildStyleStep(),
                  },
                ],
              ),
            ),
            bottomNavigationBar: _step == 1 ? null : _buildBottomBar(),
          ),
        );
      },
    );
  }

  Widget _buildSignedOut() {
    return Scaffold(
      appBar: AppBar(title: const Text('Créer ma boutique')),
      body: MessageState(
        icon: Icons.lock_outline,
        title: 'Connectez-vous pour créer votre boutique',
        message: 'Votre espace marchand vous permet de créer et de gérer vos boutiques. '
            'C’est gratuit et sans engagement.',
        action: ElevatedButton.icon(
          onPressed: () => Navigator.push<bool>(
            context,
            MaterialPageRoute(builder: (_) => const LoginScreen()),
          ),
          icon: const Icon(Icons.login),
          label: const Text('Se connecter'),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    final selected = _selectedTemplate;
    final progress = _step / _stepLabels.length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'ÉTAPE $_step SUR ${_stepLabels.length}',
          style: const TextStyle(
            color: goldInk,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.4,
          ),
        ),
        const SizedBox(height: 4),
        Text('Créons votre boutique', style: headingStyle(fontSize: 27)),
        if (_pickedFromGallery && selected != null) ...[
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Padding(
                padding: EdgeInsets.only(top: 1),
                child: Icon(Icons.check_circle_outline, size: 16, color: success),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(text: 'Modèle choisi : '),
                      TextSpan(
                        text: selected.name,
                        style: const TextStyle(fontWeight: FontWeight.w700, color: ink),
                      ),
                      TextSpan(
                        text: ' (${selected.theme.format.label}). '
                            'Vous pourrez le changer à l’étape 3.',
                      ),
                    ],
                  ),
                  style: const TextStyle(color: muted, fontSize: 13, height: 1.35),
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 14),
        TweenAnimationBuilder<double>(
          tween: Tween(end: progress),
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
          builder: (context, value, _) => ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: value,
              minHeight: 6,
              backgroundColor: border,
              color: forest,
            ),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            for (final (index, label) in _stepLabels.indexed)
              Expanded(
                child: Text(
                  '${index + 1}. $label',
                  textAlign: switch (index) {
                    0 => TextAlign.start,
                    2 => TextAlign.end,
                    _ => TextAlign.center,
                  },
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: index + 1 == _step ? FontWeight.w800 : FontWeight.w600,
                    color: index + 1 <= _step ? forest : muted,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }

  List<Widget> _buildActivityStep() {
    return [
      Card(
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            const Positioned.fill(
              child: CustomPaint(painter: MotifPainter(opacity: 0.07)),
            ),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: goldSoft,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.auto_fix_high, color: goldInk),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'Décrivez votre activité en une phrase',
                    style: headingStyle(fontSize: 21),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'L’IA vous propose un nom, un slogan, des catégories et le modèle idéal.',
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _prompt,
                    minLines: 3,
                    maxLines: 5,
                    maxLength: 500,
                    textCapitalization: TextCapitalization.sentences,
                    onChanged: (_) {
                      if (_aiError != null) setState(() => _aiError = null);
                    },
                    decoration: InputDecoration(
                      hintText: 'Ex. : Je vends des pagnes wax, des sacs et des bijoux faits main '
                          'à Abidjan.',
                      errorText: _aiError,
                      errorMaxLines: 3,
                    ),
                  ),
                  const SizedBox(height: 12),
                  LoadingButton(
                    label: 'Laisser l’IA proposer',
                    icon: Icons.auto_awesome,
                    loading: _aiLoading,
                    onPressed: _suggestWithAi,
                  ),
                  if (_aiLoading) ...[
                    const SizedBox(height: 8),
                    const Text(
                      'L’IA réfléchit…',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: muted, fontSize: 13),
                    ),
                  ],
                  const SizedBox(height: 4),
                  TextButton(
                    onPressed: _aiLoading ? null : () => _goToStep(2),
                    child: const Text('Je préfère remplir moi-même'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _buildDetailsStep() {
    return [
      FormSection(
        title: 'Les informations de votre boutique',
        children: [
          TextField(
            controller: _name,
            maxLength: 120,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.next,
            onChanged: (_) => _clearError('name'),
            decoration: InputDecoration(
              labelText: 'Nom de la boutique *',
              errorText: _error('name'),
              counterText: '',
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _tagline,
            maxLength: 160,
            textCapitalization: TextCapitalization.sentences,
            textInputAction: TextInputAction.next,
            onChanged: (_) => _clearError('tagline'),
            decoration: InputDecoration(
              labelText: 'Slogan',
              errorText: _error('tagline'),
              counterText: '',
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _sector,
            textCapitalization: TextCapitalization.sentences,
            textInputAction: TextInputAction.next,
            onChanged: (_) => _clearError('sector'),
            decoration: InputDecoration(
              labelText: 'Secteur d’activité',
              hintText: 'Ex. : Mode, Beauté, Alimentation',
              errorText: _error('sector'),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _description,
            minLines: 3,
            maxLines: 6,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (_) => _clearError('description'),
            decoration: InputDecoration(
              labelText: 'Description',
              alignLabelWithHint: true,
              errorText: _error('description'),
            ),
          ),
          const SizedBox(height: 18),
          _buildCategoriesField(),
        ],
      ),
    ];
  }

  Widget _buildCategoriesField() {
    final full = _categories.length >= _maxCategories;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text('Catégories', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
            Text(
              '${_categories.length}/$_maxCategories',
              style: const TextStyle(color: muted, fontSize: 12),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (_categories.isNotEmpty) ...[
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final category in _categories)
                InputChip(
                  label: Text(category),
                  backgroundColor: forestSoft,
                  side: BorderSide.none,
                  labelStyle: const TextStyle(color: forest, fontWeight: FontWeight.w600),
                  deleteIconColor: forest,
                  deleteButtonTooltipMessage: 'Retirer $category',
                  onDeleted: () => _removeCategory(category),
                ),
            ],
          ),
          const SizedBox(height: 10),
        ],
        TextField(
          controller: _categoryDraft,
          focusNode: _categoryFocus,
          enabled: !full,
          maxLength: 60,
          textCapitalization: TextCapitalization.sentences,
          textInputAction: TextInputAction.done,
          onChanged: (value) {
            if (value.contains(',')) _commitCategory();
          },
          onSubmitted: (_) {
            _commitCategory();
            _categoryFocus.requestFocus();
          },
          decoration: InputDecoration(
            hintText: full ? 'Maximum atteint' : 'Ajoutez une catégorie puis Entrée',
            helperText: 'Appuyez sur Entrée pour ajouter, touchez × pour retirer.',
            errorText: _error('categories'),
            counterText: '',
            suffixIcon: IconButton(
              tooltip: 'Ajouter la catégorie',
              onPressed: full ? null : _commitCategory,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ),
        ),
      ],
    );
  }

  List<Widget> _buildStyleStep() {
    final selected = _selectedTemplate;
    final title = _name.text.trim().isNotEmpty ? _name.text.trim() : (selected?.name ?? 'Ma boutique');
    final tagline = _tagline.text.trim();
    return [
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Aperçu en direct', style: headingStyle(fontSize: 18)),
              const SizedBox(height: 12),
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                child: ThemePreview(
                  key: ValueKey('${_template}_${_primaryOverride?.toARGB32()}'),
                  theme: _previewTheme,
                  title: title,
                  subtitle: tagline.isEmpty ? selected?.sector : tagline,
                  height: 210,
                ),
              ),
              if (selected != null) ...[
                const SizedBox(height: 12),
                Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(
                        text: selected.name,
                        style: const TextStyle(fontWeight: FontWeight.w700, color: ink),
                      ),
                      TextSpan(text: ' · format ${selected.theme.format.label}'),
                      if (selected.idealFor.isNotEmpty)
                        TextSpan(text: '\nIdéal pour : ${selected.idealFor.join(', ')}'),
                    ],
                  ),
                  style: const TextStyle(color: muted, fontSize: 13, height: 1.4),
                ),
                const Divider(height: 28),
                _buildColorPicker(selected),
              ],
            ],
          ),
        ),
      ),
      const SizedBox(height: 22),
      Text('Choisissez votre modèle', style: headingStyle(fontSize: 21)),
      const SizedBox(height: 4),
      const Text(
        'Chaque modèle a sa structure de page. Vous pourrez tout modifier plus tard depuis '
        'votre tableau de bord.',
        style: TextStyle(color: muted, height: 1.4),
      ),
      const SizedBox(height: 14),
      ..._buildGallery(),
    ];
  }

  Widget _buildColorPicker(ShopTemplate template) {
    final templatePrimary = template.theme.primary;
    final current = _primaryOverride ?? templatePrimary;
    final swatches = <Color>[templatePrimary];
    for (final color in _presetColors) {
      if (swatches.every((swatch) => colorToHex(swatch) != colorToHex(color))) {
        swatches.add(color);
      }
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text('Couleur principale', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
            if (_primaryOverride != null)
              TextButton(
                onPressed: () => setState(() => _primaryOverride = null),
                child: const Text('Couleur du modèle'),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final (index, color) in swatches.indexed)
              _ColorSwatch(
                color: color,
                selected: colorToHex(color) == colorToHex(current),
                isTemplateColor: index == 0,
                onTap: () => _choosePrimary(color),
              ),
          ],
        ),
      ],
    );
  }

  List<Widget> _buildGallery() {
    final templates = _templates;
    if (_templatesLoading && templates == null) {
      return const [
        Padding(
          padding: EdgeInsets.symmetric(vertical: 32),
          child: LoadingView(text: 'Chargement des modèles…'),
        ),
      ];
    }
    if (_templatesError != null || templates == null || templates.isEmpty) {
      return [
        MessageState(
          icon: Icons.palette_outlined,
          title: 'Modèles indisponibles',
          message: 'Impossible de charger les modèles pour le moment.',
          onRetry: _loadTemplates,
        ),
      ];
    }
    final filtered = _filteredTemplates;
    return [
      SizedBox(
        height: 40,
        child: ListView(
          scrollDirection: Axis.horizontal,
          children: [
            for (final format in <ShopFormat?>[null, ...ShopFormat.values])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(format?.label ?? 'Tous'),
                  avatar: format == null ? null : Icon(format.icon, size: 16),
                  selected: _formatFilter == format,
                  showCheckmark: false,
                  labelStyle: TextStyle(
                    color: _formatFilter == format ? ivory : ink,
                    fontWeight: FontWeight.w600,
                  ),
                  iconTheme: IconThemeData(color: _formatFilter == format ? ivory : goldInk),
                  onSelected: (_) => setState(() => _formatFilter = format),
                ),
              ),
          ],
        ),
      ),
      const SizedBox(height: 14),
      if (filtered.isEmpty)
        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            const Text('Aucun modèle pour ce format.', style: TextStyle(color: muted)),
            TextButton(
              onPressed: () => setState(() => _formatFilter = null),
              child: const Text('Voir tous les modèles'),
            ),
          ],
        ),
      for (final template in filtered)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TemplateCard(
            template: _withPreviewTheme(template),
            selected: template.key == _template,
            recommended: template.key == _recommendedTemplate,
            onTap: () => _chooseTemplate(template),
          ),
        ),
      if (_error('template') case final message?) ...[
        const SizedBox(height: 4),
        NoticeBanner(message: message),
      ],
    ];
  }

  /// The selected card shows the custom primary color, like the web thumbnail.
  ShopTemplate _withPreviewTheme(ShopTemplate template) {
    if (template.key != _template || _primaryOverride == null) return template;
    return ShopTemplate(
      key: template.key,
      name: template.name,
      sector: template.sector,
      description: template.description,
      idealFor: template.idealFor,
      theme: _previewTheme,
      defaultCategories: template.defaultCategories,
    );
  }

  Widget _buildBottomBar() {
    final isLast = _step == 3;
    return Material(
      color: Colors.white,
      child: Container(
        decoration: const BoxDecoration(border: Border(top: BorderSide(color: border))),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            child: Row(
              children: [
                OutlinedButton.icon(
                  onPressed: _submitting ? null : () => setState(() => _step--),
                  icon: const Icon(Icons.arrow_back, size: 18),
                  label: const Text('Retour'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: isLast
                      ? LoadingButton(
                          label: 'Créer ma boutique',
                          icon: Icons.rocket_launch_outlined,
                          loading: _submitting,
                          onPressed: _selectedTemplate == null ? null : _submit,
                        )
                      : ElevatedButton(
                          onPressed: () => _goToStep(3),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text('Continuer'),
                              SizedBox(width: 6),
                              Icon(Icons.arrow_forward, size: 18),
                            ],
                          ),
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ColorSwatch extends StatelessWidget {
  const _ColorSwatch({
    required this.color,
    required this.selected,
    required this.isTemplateColor,
    required this.onTap,
  });

  final Color color;
  final bool selected;
  final bool isTemplateColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tooltip = isTemplateColor
        ? 'Couleur du modèle (${colorToHex(color)})'
        : colorToHex(color);
    return Tooltip(
      message: tooltip,
      child: Semantics(
        button: true,
        selected: selected,
        label: tooltip,
        child: GestureDetector(
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            width: 40,
            height: 40,
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: selected ? gold : border, width: selected ? 2.5 : 1),
            ),
            child: DecoratedBox(
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              child: selected
                  ? Icon(Icons.check, size: 18, color: contrastingText(color))
                  : isTemplateColor
                  ? Icon(Icons.star_rounded, size: 14, color: contrastingText(color))
                  : null,
            ),
          ),
        ),
      ),
    );
  }
}

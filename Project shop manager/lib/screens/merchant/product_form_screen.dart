import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/format.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import 'tabs/products_tab.dart' show ModerationDetails;

const _maxUploadBytes = 4 * 1024 * 1024;
const _acceptedExtensions = {'jpg', 'jpeg', 'png', 'webp'};

/// Lets the merchant pick a photo from [source] and uploads it to the shop
/// (`POST /shops/{id}/uploads`), like the web `useImageUpload` composable.
///
/// Returns the public URL, or `null` when the merchant cancelled the picker.
/// Throws an [ApiException] with a user-facing message on failure.
Future<String?> pickAndUploadShopImage(
  BuildContext context, {
  required int shopId,
  required ImageSource source,
}) async {
  final api = context.api;
  final XFile? file;
  try {
    file = await ImagePicker().pickImage(source: source, maxWidth: 1600, imageQuality: 85);
  } on Exception {
    throw ApiException(
      source == ImageSource.camera
          ? 'Impossible d’ouvrir l’appareil photo. Vérifiez les autorisations de l’application.'
          : 'Impossible d’ouvrir la galerie. Vérifiez les autorisations de l’application.',
    );
  }
  if (file == null) return null;

  final name = file.name.isEmpty ? 'photo.jpg' : file.name;
  final extension = name.contains('.') ? name.split('.').last.toLowerCase() : '';
  if (extension.isNotEmpty && !_acceptedExtensions.contains(extension)) {
    throw const ApiException('Format non pris en charge : utilisez une image JPEG, PNG ou WebP.');
  }
  final bytes = await file.readAsBytes();
  if (bytes.length > _maxUploadBytes) {
    throw const ApiException('L’image dépasse 4 Mo. Choisissez un fichier plus léger.');
  }
  try {
    return await api.uploadImage(shopId, bytes, extension.isEmpty ? '$name.jpg' : name);
  } on ApiException catch (error) {
    throw ApiException(error.fieldError('image') ?? error.message, statusCode: error.statusCode);
  }
}

/// Bottom sheet asking whether to take a photo or pick one from the gallery.
Future<ImageSource?> askImageSource(BuildContext context) => showModalBottomSheet<ImageSource>(
  context: context,
  builder: (context) => SafeArea(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        ListTile(
          leading: const Icon(Icons.photo_library_outlined, color: forest),
          title: const Text('Choisir dans la galerie'),
          onTap: () => Navigator.pop(context, ImageSource.gallery),
        ),
        ListTile(
          leading: const Icon(Icons.photo_camera_outlined, color: forest),
          title: const Text('Prendre une photo'),
          onTap: () => Navigator.pop(context, ImageSource.camera),
        ),
        const SizedBox(height: 8),
      ],
    ),
  ),
);

/// Creation / edition of a product, mirroring `components/dashboard/ProductForm.vue`
/// and the `produits/nouveau.vue` / `produits/[productId].vue` pages.
///
/// Pops the saved [Product].
class ProductFormScreen extends StatefulWidget {
  const ProductFormScreen({required this.shop, this.product, super.key});

  final Shop shop;
  final Product? product;

  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  static const _maxTags = 15;
  static const _maxTagLength = 40;
  static const _tones = {'professionnel': 'Professionnel', 'decontracte': 'Décontracté', 'luxe': 'Luxe'};

  late final Product? _product = widget.product;
  late final _name = TextEditingController(text: _product?.name ?? '');
  late final _price = TextEditingController(text: _numberText(_product?.price));
  late final _compareAt = TextEditingController(text: _numberText(_product?.compareAtPrice));
  late final _stock = TextEditingController(text: '${_product?.stockQuantity ?? 1}');
  late final _description = TextEditingController(text: _product?.description ?? '');
  late final _imageUrl = TextEditingController(text: _product?.imageUrl ?? '');
  final _newCategory = TextEditingController();
  final _tagDraft = TextEditingController();
  final _features = TextEditingController();

  late int? _categoryId = _product?.categoryId;
  late final List<String> _tags = [...?_product?.tags];
  late String _status = _product?.status == 'draft' ? 'draft' : 'published';
  late bool _featured = _product?.isFeatured ?? false;
  String _tone = 'professionnel';

  List<Category> _categories = const [];
  bool _loadingCategories = true;
  bool _addingCategory = false;
  bool _uploading = false;
  bool _generating = false;
  bool _saving = false;
  String? _uploadError;
  String? _tagError;
  Map<String, String> _errors = const {};

  bool get _isEdit => _product != null;
  String get _unit => currencyUnit(widget.shop.currency);

  static String _numberText(double? value) {
    if (value == null) return '';
    return value == value.roundToDouble() ? value.toInt().toString() : value.toString();
  }

  static double? _parseNumber(String text) {
    final cleaned = text.replaceAll(RegExp(r'[\s  ]'), '').replaceAll(',', '.');
    return cleaned.isEmpty ? null : double.tryParse(cleaned);
  }

  @override
  void initState() {
    super.initState();
    _imageUrl.addListener(_onImageUrlChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadCategories());
  }

  @override
  void dispose() {
    _imageUrl.removeListener(_onImageUrlChanged);
    for (final controller in [
      _name,
      _price,
      _compareAt,
      _stock,
      _description,
      _imageUrl,
      _newCategory,
      _tagDraft,
      _features,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _onImageUrlChanged() => setState(() {});

  Future<void> _loadCategories() async {
    try {
      final categories = await context.api.getCategories(widget.shop.id);
      if (!mounted) return;
      setState(() => _categories = categories);
    } on Object {
      if (!mounted) return;
      // Fall back to the categories embedded in the shop resource.
      setState(() => _categories = widget.shop.categories);
    } finally {
      if (mounted) setState(() => _loadingCategories = false);
    }
  }

  Future<void> _addCategory() async {
    final name = _newCategory.text.trim();
    if (name.isEmpty || _addingCategory) return;
    setState(() => _addingCategory = true);
    try {
      final category = await context.api.createCategory(widget.shop.id, name);
      if (!mounted) return;
      setState(() {
        _categories = [..._categories.where((item) => item.id != category.id), category];
        _categoryId = category.id;
        _newCategory.clear();
      });
      showToast(context, 'Catégorie « ${category.name} » ajoutée.');
    } on ApiException catch (error) {
      if (mounted) showToast(context, error.fieldError('name') ?? error.message, error: true);
    } on Object catch (error) {
      if (mounted) showToast(context, errorText(error), error: true);
    } finally {
      if (mounted) setState(() => _addingCategory = false);
    }
  }

  Future<void> _pickImage() async {
    final source = await askImageSource(context);
    if (source == null || !mounted) return;
    setState(() {
      _uploading = true;
      _uploadError = null;
    });
    try {
      final url = await pickAndUploadShopImage(context, shopId: widget.shop.id, source: source);
      if (!mounted || url == null) return;
      _imageUrl.text = url;
      setState(() => _errors = {..._errors}..remove('image_url'));
      showToast(context, 'Photo téléversée.');
    } on Object catch (error) {
      if (mounted) setState(() => _uploadError = errorText(error, 'Le téléversement a échoué.'));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  void _commitTag() {
    final value = _tagDraft.text.replaceAll(',', '').trim();
    _tagDraft.clear();
    if (value.isEmpty) return;
    setState(() {
      _tagError = null;
      if (value.length > _maxTagLength) {
        _tagError = 'Chaque mot-clé doit faire $_maxTagLength caractères maximum.';
      } else if (_tags.length >= _maxTags) {
        _tagError = 'Vous pouvez ajouter $_maxTags mots-clés maximum.';
      } else if (!_tags.any((tag) => tag.toLowerCase() == value.toLowerCase())) {
        _tags.add(value);
      }
    });
  }

  Future<void> _generate() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(
        () =>
            _errors = {..._errors, 'name': 'Indiquez d’abord le nom du produit pour que l’IA puisse écrire.'},
      );
      return;
    }
    setState(() => _generating = true);
    try {
      final copy = await context.api.generateProductDescription(
        name: name,
        features: _features.text,
        tone: _tone,
      );
      if (!mounted) return;
      setState(() {
        _description.text = copy.description;
        for (final tag in copy.tags) {
          final value = tag.trim();
          if (value.isEmpty || value.length > _maxTagLength || _tags.length >= _maxTags) continue;
          if (!_tags.any((existing) => existing.toLowerCase() == value.toLowerCase())) {
            _tags.add(value);
          }
        }
      });
      showToast(context, 'Description générée ! Relisez-la et ajustez si besoin.');
    } on Object catch (error) {
      if (mounted) {
        showToast(context, errorText(error, 'L’IA n’a pas pu générer la description.'), error: true);
      }
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  Map<String, String> _validate() {
    final errors = <String, String>{};
    if (_name.text.trim().isEmpty) errors['name'] = 'Le nom du produit est obligatoire.';
    final price = _parseNumber(_price.text);
    if (price == null) {
      errors['price'] = 'Indiquez un prix valide.';
    } else if (price < 0) {
      errors['price'] = 'Le prix ne peut pas être négatif.';
    }
    if (_compareAt.text.trim().isNotEmpty && (_parseNumber(_compareAt.text) ?? -1) < 0) {
      errors['compare_at_price'] = 'Indiquez un prix barré valide.';
    }
    final stock = _stock.text.trim();
    if (stock.isNotEmpty && (int.tryParse(stock) ?? -1) < 0) {
      errors['stock_quantity'] = 'Indiquez un stock valide (nombre entier).';
    }
    return errors;
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (_tagDraft.text.trim().isNotEmpty) _commitTag();
    final localErrors = _validate();
    if (localErrors.isNotEmpty) {
      setState(() => _errors = localErrors);
      showToast(context, localErrors.values.first, error: true);
      return;
    }

    final compareAt = _parseNumber(_compareAt.text);
    final imageUrl = _imageUrl.text.trim();
    final description = _description.text.trim();
    final body = <String, dynamic>{
      'name': _name.text.trim(),
      'price': _parseNumber(_price.text),
      'compare_at_price': compareAt == null || compareAt == 0 ? null : compareAt,
      'stock_quantity': int.tryParse(_stock.text.trim()) ?? 0,
      'category_id': _categoryId,
      'image_url': imageUrl.isEmpty ? null : imageUrl,
      'tags': _tags,
      'status': _status,
      'is_featured': _featured,
      'description': description.isEmpty ? null : description,
    };

    setState(() {
      _saving = true;
      _errors = const {};
    });
    try {
      final saved = await context.api.saveProduct(widget.shop.id, body, productId: _product?.id);
      if (!mounted) return;
      showToast(
        context,
        _isEdit
            ? 'Modifications enregistrées et revérifiées par l’agent IA.'
            : '« ${saved.name} » enregistré. Notre agent IA l’a analysé.',
      );
      Navigator.pop(context, saved);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(
        () => _errors = error.fieldErrors.map(
          (key, value) => MapEntry(key.startsWith('tags.') ? 'tags' : key, value),
        ),
      );
      const shown = {
        'name',
        'price',
        'compare_at_price',
        'stock_quantity',
        'category_id',
        'image_url',
        'tags',
        'status',
        'description',
      };
      if (!_errors.keys.any(shown.contains)) {
        showToast(
          context,
          error.isValidation ? error.message : errorText(error, 'Le produit n’a pas pu être enregistré.'),
          error: true,
        );
      }
    } on Object catch (error) {
      if (mounted) {
        showToast(context, errorText(error, 'Le produit n’a pas pu être enregistré.'), error: true);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _isEdit ? _product!.name : 'Nouveau produit',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            if (_isEdit)
              _ModerationSection(product: _product!)
            else
              const NoticeBanner(
                tone: Tone.info,
                icon: Icons.verified_user_outlined,
                message:
                    'Notre agent IA vérifie chaque produit avant qu’il soit visible du public. '
                    'C’est ce qui rend les boutiques ShopAI dignes de confiance.',
              ),
            const SizedBox(height: 16),
            _informationSection(),
            const SizedBox(height: 16),
            _imageSection(),
            const SizedBox(height: 16),
            _descriptionSection(),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          decoration: const BoxDecoration(
            color: ivory,
            border: Border(top: BorderSide(color: border)),
          ),
          child: LoadingButton(
            label: _isEdit ? 'Enregistrer les modifications' : 'Enregistrer et faire vérifier',
            icon: Icons.check,
            loading: _saving,
            onPressed: _uploading || _generating ? null : _submit,
          ),
        ),
      ),
    );
  }

  Widget _informationSection() {
    return FormSection(
      title: 'Informations',
      children: [
        TextField(
          controller: _name,
          maxLength: 160,
          textCapitalization: TextCapitalization.sentences,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: 'Nom du produit *',
            counterText: '',
            errorText: _errors['name'],
          ),
          onChanged: (_) {
            if (_errors.containsKey('name')) setState(() => _errors = {..._errors}..remove('name'));
          },
        ),
        const SizedBox(height: 12),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: TextField(
                controller: _price,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: 'Prix ($_unit) *',
                  errorText: _errors['price'],
                  errorMaxLines: 2,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                controller: _compareAt,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: 'Prix barré ($_unit)',
                  helperText: 'Optionnel, pour afficher une promotion.',
                  helperMaxLines: 2,
                  errorText: _errors['compare_at_price'],
                  errorMaxLines: 2,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _stock,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: 'Stock',
            prefixIcon: const Icon(Icons.inventory_2_outlined, size: 20),
            errorText: _errors['stock_quantity'],
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int?>(
          key: ValueKey('category-$_categoryId-${_categories.length}'),
          initialValue: _categories.any((category) => category.id == _categoryId) ? _categoryId : null,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: 'Catégorie',
            errorText: _errors['category_id'],
            suffixIcon: _loadingCategories
                ? const Padding(
                    padding: EdgeInsets.all(14),
                    child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                  )
                : null,
          ),
          items: [
            const DropdownMenuItem<int?>(value: null, child: Text('Sans catégorie')),
            for (final category in _categories)
              DropdownMenuItem<int?>(
                value: category.id,
                child: Text(category.name, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (value) => setState(() => _categoryId = value),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _newCategory,
                textCapitalization: TextCapitalization.sentences,
                textInputAction: TextInputAction.done,
                decoration: const InputDecoration(
                  labelText: 'Nouvelle catégorie',
                  hintText: 'Ex. : Accessoires',
                ),
                onChanged: (_) => setState(() {}),
                onSubmitted: (_) => _addCategory(),
              ),
            ),
            const SizedBox(width: 8),
            OutlinedButton(
              onPressed: _addingCategory || _newCategory.text.trim().isEmpty ? null : _addCategory,
              child: _addingCategory
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Ajouter'),
            ),
          ],
        ),
        const SizedBox(height: 16),
        const Text('Statut', style: TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'published', icon: Icon(Icons.public), label: Text('Publié')),
            ButtonSegment(value: 'draft', icon: Icon(Icons.edit_outlined), label: Text('Brouillon')),
          ],
          selected: {_status},
          onSelectionChanged: (selection) => setState(() => _status = selection.first),
        ),
        if (_errors['status'] != null) ...[
          const SizedBox(height: 6),
          Text(_errors['status']!, style: const TextStyle(color: danger, fontSize: 12)),
        ],
        const SizedBox(height: 8),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _featured,
          onChanged: (value) => setState(() => _featured = value),
          title: const Text('Produit vedette', style: TextStyle(fontWeight: FontWeight.w600)),
          subtitle: const Text(
            'Mis en avant sur la page d’accueil de votre boutique.',
            style: TextStyle(color: muted, fontSize: 12.5),
          ),
        ),
      ],
    );
  }

  Widget _imageSection() {
    final url = _imageUrl.text.trim();
    return FormSection(
      title: 'Image',
      subtitle: 'JPEG, PNG ou WebP — 4 Mo maximum.',
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Stack(
            children: [
              ProductImage(
                url: url.isEmpty ? null : url,
                color: widget.shop.theme.primary,
                aspectRatio: 4 / 3,
              ),
              if (_uploading)
                Positioned.fill(
                  child: Container(
                    color: Colors.white.withValues(alpha: 0.7),
                    alignment: Alignment.center,
                    child: const LoadingView(text: 'Téléversement en cours…'),
                  ),
                ),
              if (url.isNotEmpty && !_uploading)
                Positioned(
                  top: 8,
                  right: 8,
                  child: IconButton.filled(
                    tooltip: 'Retirer la photo',
                    style: IconButton.styleFrom(backgroundColor: ink.withValues(alpha: 0.65)),
                    icon: const Icon(Icons.close, color: Colors.white, size: 20),
                    onPressed: () => _imageUrl.clear(),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _uploading ? null : _pickImage,
          icon: const Icon(Icons.add_a_photo_outlined),
          label: Text(url.isEmpty ? 'Téléverser une photo' : 'Changer la photo'),
        ),
        if (_uploadError != null) ...[const SizedBox(height: 10), NoticeBanner(message: _uploadError!)],
        const SizedBox(height: 14),
        TextField(
          controller: _imageUrl,
          keyboardType: TextInputType.url,
          decoration: InputDecoration(
            labelText: 'Ou collez l’URL d’une image',
            hintText: 'https://…',
            errorText: _errors['image_url'],
          ),
        ),
      ],
    );
  }

  Widget _descriptionSection() {
    return FormSection(
      title: 'Description',
      subtitle: 'Laissez l’IA rédiger une fiche produit qui donne envie d’acheter.',
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: goldSoft,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: gold.withValues(alpha: 0.35)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Row(
                children: [
                  Icon(Icons.auto_awesome, color: goldInk, size: 18),
                  SizedBox(width: 6),
                  Text(
                    'Assistant de rédaction',
                    style: TextStyle(color: goldInk, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _features,
                minLines: 2,
                maxLines: 4,
                maxLength: 2000,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Points clés (optionnel)',
                  hintText: 'Ex. : 100 % coton, fait main, taille unique',
                  counterText: '',
                ),
              ),
              const SizedBox(height: 10),
              const Text('Ton', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  for (final entry in _tones.entries)
                    ChoiceChip(
                      label: Text(entry.value),
                      selected: _tone == entry.key,
                      labelStyle: TextStyle(
                        color: _tone == entry.key ? ivory : ink,
                        fontWeight: FontWeight.w600,
                      ),
                      onSelected: (_) => setState(() => _tone = entry.key),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              LoadingButton(
                label: 'Générer avec l’IA',
                icon: Icons.auto_awesome,
                loading: _generating,
                onPressed: _generate,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _description,
          minLines: 6,
          maxLines: 14,
          maxLength: 10000,
          enabled: !_generating,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            labelText: 'Texte de la fiche',
            alignLabelWithHint: true,
            counterText: '',
            errorText: _errors['description'],
          ),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            const Expanded(
              child: Text('Mots-clés', style: TextStyle(fontWeight: FontWeight.w600)),
            ),
            Text('${_tags.length}/$_maxTags', style: const TextStyle(color: muted, fontSize: 12.5)),
          ],
        ),
        const SizedBox(height: 8),
        if (_tags.isNotEmpty) ...[
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final tag in _tags)
                InputChip(
                  label: Text(tag),
                  backgroundColor: forestSoft,
                  side: BorderSide.none,
                  labelStyle: const TextStyle(color: forest, fontWeight: FontWeight.w600),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  deleteIconColor: forest,
                  deleteButtonTooltipMessage: 'Retirer $tag',
                  onDeleted: () => setState(() {
                    _tags.remove(tag);
                    _tagError = null;
                  }),
                ),
            ],
          ),
          const SizedBox(height: 10),
        ],
        TextField(
          controller: _tagDraft,
          enabled: _tags.length < _maxTags,
          maxLength: _maxTagLength,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(
            hintText: _tags.length < _maxTags
                ? 'Ajoutez un mot-clé puis Entrée'
                : 'Nombre maximum de mots-clés atteint',
            counterText: '',
            errorText: _tagError ?? _errors['tags'],
            suffixIcon: IconButton(
              tooltip: 'Ajouter le mot-clé',
              icon: const Icon(Icons.add),
              onPressed: _tags.length < _maxTags ? _commitTag : null,
            ),
          ),
          onChanged: (value) {
            if (value.contains(',')) _commitTag();
          },
          onSubmitted: (_) => _commitTag(),
        ),
      ],
    );
  }
}

class _ModerationSection extends StatelessWidget {
  const _ModerationSection({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final (tone, icon, text) = switch (product.moderation.status) {
      ModerationStatus.approved => (
        Tone.success,
        Icons.verified_user_outlined,
        'Produit vérifié : il est visible par vos clients (s’il est publié).',
      ),
      ModerationStatus.rejected => (
        Tone.danger,
        Icons.gpp_bad_outlined,
        'Produit refusé : corrigez les points ci-dessous puis relancez la vérification.',
      ),
      _ => (
        Tone.warning,
        Icons.hourglass_top,
        'Vérification en cours : un modérateur examine ce produit. Il sera visible dès validation.',
      ),
    };
    return FormSection(
      title: 'Vérification IA',
      subtitle:
          'Notre agent IA vérifie chaque produit avant qu’il soit visible du public : '
          'contrefaçons, produits interdits, prix suspects…',
      children: [
        NoticeBanner(tone: tone, icon: icon, message: text),
        const SizedBox(height: 12),
        ModerationDetails(moderation: product.moderation),
      ],
    );
  }
}

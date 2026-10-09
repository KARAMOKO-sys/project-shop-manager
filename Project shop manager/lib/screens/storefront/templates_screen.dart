import 'package:flutter/material.dart';

import '../../app/navigation.dart';
import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../models/models.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import '../../widgets/theme_preview.dart';
import '../merchant/create_shop_screen.dart';

/// Template gallery filtered by storefront format, mirroring `pages/modeles.vue`.
class TemplatesScreen extends StatefulWidget {
  const TemplatesScreen({super.key});

  @override
  State<TemplatesScreen> createState() => _TemplatesScreenState();
}

class _TemplatesScreenState extends State<TemplatesScreen> {
  late Future<List<ShopTemplate>> _templates = context.api.getTemplates();
  ShopFormat? _format;

  Future<void> _refresh() async {
    final future = context.api.getTemplates();
    setState(() => _templates = future);
    try {
      await future;
    } on Object {
      // Rendered by the FutureBuilder.
    }
  }

  void _showDetails(ShopTemplate template) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ThemePreview(theme: template.theme, title: template.name, subtitle: template.sector, height: 240),
              const SizedBox(height: 16),
              Text(template.name, style: headingStyle(fontSize: 24)),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(template.theme.format.icon, size: 16, color: goldInk),
                  const SizedBox(width: 5),
                  Text(
                    'Format ${template.theme.format.label.toLowerCase()} · ${template.sector}',
                    style: const TextStyle(color: goldInk, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(template.description, style: const TextStyle(color: muted, height: 1.45)),
              if (template.idealFor.isNotEmpty) ...[
                const SizedBox(height: 14),
                const Text('Idéal pour', style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [for (final item in template.idealFor) Chip(label: Text(item))],
                ),
              ],
              if (template.defaultCategories.isNotEmpty) ...[
                const SizedBox(height: 14),
                const Text('Catégories proposées', style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text(template.defaultCategories.join(' · '), style: const TextStyle(color: muted)),
              ],
              const SizedBox(height: 18),
              ElevatedButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  pushScreen(this.context, CreateShopScreen(templateKey: template.key));
                },
                icon: const Icon(Icons.auto_awesome),
                label: const Text('Utiliser ce modèle'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Modèles de boutique')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<ShopTemplate>>(
          future: _templates,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 80),
                  MessageState(
                    icon: Icons.palette_outlined,
                    title: 'Modèles indisponibles',
                    message: errorText(snapshot.error!),
                    onRetry: _refresh,
                  ),
                ],
              );
            }
            final templates = snapshot.data;
            if (templates == null) return const LoadingView(text: 'Chargement des modèles…');
            final filtered = _format == null
                ? templates
                : templates.where((template) => template.theme.format == _format).toList();
            int count(ShopFormat format) => templates.where((template) => template.theme.format == format).length;

            return CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                const SliverPadding(
                  padding: EdgeInsets.fromLTRB(20, 4, 20, 12),
                  sliver: SliverToBoxAdapter(
                    child: Text(
                      'Classique, éditorial, catalogue, menu, vitrine ou produit unique : choisissez le modèle qui '
                      'correspond à votre activité. Couleurs, police et arrondis restent personnalisables.',
                      style: TextStyle(color: muted, height: 1.45),
                    ),
                  ),
                ),
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: 46,
                    child: ListView(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      scrollDirection: Axis.horizontal,
                      children: [
                        _chip(null, 'Tous (${templates.length})', Icons.apps),
                        for (final format in ShopFormat.values)
                          if (count(format) > 0) _chip(format, '${format.label} (${count(format)})', format.icon),
                      ],
                    ),
                  ),
                ),
                if (_format != null)
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                    sliver: SliverToBoxAdapter(
                      child: NoticeBanner(message: _format!.description, tone: Tone.gold, icon: _format!.icon),
                    ),
                  ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
                  sliver: SliverList.separated(
                    itemCount: filtered.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 14),
                    itemBuilder: (context, index) {
                      final template = filtered[index];
                      return TemplateCard(
                        template: template,
                        onTap: () => _showDetails(template),
                        action: Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => _showDetails(template),
                                child: const Text('Aperçu'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: ElevatedButton(
                                onPressed: () => pushScreen(context, CreateShopScreen(templateKey: template.key)),
                                style: ElevatedButton.styleFrom(minimumSize: const Size(44, 46)),
                                child: const Text('Utiliser'),
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _chip(ShopFormat? format, String label, IconData icon) {
    final selected = _format == format;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        avatar: Icon(icon, size: 16, color: selected ? ivory : forest),
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        labelStyle: TextStyle(color: selected ? ivory : ink, fontWeight: FontWeight.w600),
        onSelected: (_) => setState(() => _format = format),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../app/theme.dart';
import '../models/models.dart';

/// Miniature storefront rendered with a [ShopTheme] (like the web `ThemePreview`).
class ThemePreview extends StatelessWidget {
  const ThemePreview({
    required this.theme,
    required this.title,
    this.subtitle,
    this.height = 190,
    super.key,
  });

  final ShopTheme theme;
  final String title;
  final String? subtitle;
  final double height;

  TextStyle _font(TextStyle style) {
    try {
      return GoogleFonts.getFont(theme.font, textStyle: style);
    } on Exception {
      return style;
    }
  }

  @override
  Widget build(BuildContext context) {
    final onPrimary = contrastingText(theme.primary);
    final radius = theme.radius.clamp(0, 16).toDouble();
    final hero = switch (theme.heroStyle) {
      'minimal' => BoxDecoration(color: theme.surface, border: Border(bottom: BorderSide(color: theme.text.withValues(alpha: 0.08)))),
      'image' => BoxDecoration(
        gradient: LinearGradient(
          colors: [theme.primary.withValues(alpha: 0.85), theme.secondary.withValues(alpha: 0.85)],
          begin: Alignment.bottomLeft,
          end: Alignment.topRight,
        ),
      ),
      _ => BoxDecoration(
        gradient: LinearGradient(
          colors: [theme.primary, Color.lerp(theme.primary, theme.secondary, 0.55)!],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
    };
    final heroText = theme.heroStyle == 'minimal' ? theme.text : onPrimary;

    return Container(
      height: height,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: theme.background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            height: 22,
            color: theme.surface,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              children: [
                Container(width: 9, height: 9, decoration: BoxDecoration(color: theme.primary, borderRadius: BorderRadius.circular(3))),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: _font(TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: theme.text)),
                  ),
                ),
                Icon(Icons.shopping_bag_outlined, size: 11, color: theme.primary),
              ],
            ),
          ),
          Expanded(
            flex: 5,
            child: Container(
              decoration: hero,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              alignment: Alignment.centerLeft,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: _font(TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: heroText)),
                  ),
                  if (subtitle != null)
                    Text(
                      subtitle!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: _font(TextStyle(fontSize: 9, color: heroText.withValues(alpha: 0.8))),
                    ),
                  const SizedBox(height: 5),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: theme.heroStyle == 'minimal' ? theme.primary : theme.secondary,
                      borderRadius: BorderRadius.circular(radius),
                    ),
                    child: Text(
                      'Découvrir',
                      style: _font(TextStyle(
                        fontSize: 8,
                        fontWeight: FontWeight.w700,
                        color: contrastingText(theme.heroStyle == 'minimal' ? theme.primary : theme.secondary),
                      )),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            flex: 6,
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: theme.layout == 'list' || theme.format == ShopFormat.menu
                  ? Column(children: [for (var i = 0; i < 3; i++) Expanded(child: _listRow(radius))])
                  : Row(children: [for (var i = 0; i < 3; i++) Expanded(child: _tile(radius, i))]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tile(double radius, int index) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 3),
    decoration: BoxDecoration(
      color: theme.surface,
      borderRadius: BorderRadius.circular(radius * 0.6),
      border: Border.all(color: theme.text.withValues(alpha: 0.06)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Container(
            decoration: BoxDecoration(
              color: (index.isEven ? theme.primary : theme.secondary).withValues(alpha: 0.16),
              borderRadius: BorderRadius.vertical(top: Radius.circular(radius * 0.6)),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(height: 3, width: 28, color: theme.text.withValues(alpha: 0.3)),
              const SizedBox(height: 3),
              Container(height: 3, width: 16, color: theme.primary),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _listRow(double radius) => Container(
    margin: const EdgeInsets.symmetric(vertical: 2),
    padding: const EdgeInsets.symmetric(horizontal: 5),
    decoration: BoxDecoration(color: theme.surface, borderRadius: BorderRadius.circular(radius * 0.5)),
    child: Row(
      children: [
        Container(width: 14, height: 14, decoration: BoxDecoration(color: theme.secondary.withValues(alpha: 0.3), borderRadius: BorderRadius.circular(4))),
        const SizedBox(width: 6),
        Expanded(child: Container(height: 3, color: theme.text.withValues(alpha: 0.3))),
        const SizedBox(width: 18),
        Container(height: 3, width: 14, color: theme.primary),
      ],
    ),
  );
}

/// Card used in the template galleries.
class TemplateCard extends StatelessWidget {
  const TemplateCard({required this.template, this.selected = false, this.recommended = false, this.onTap, this.action, super.key});

  final ShopTemplate template;
  final bool selected;
  final bool recommended;
  final VoidCallback? onTap;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: selected ? gold : border, width: selected ? 2 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  ThemePreview(theme: template.theme, title: template.name, subtitle: template.sector, height: 150),
                  if (selected || recommended)
                    Positioned(
                      top: 30,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: selected ? gold : forest, borderRadius: BorderRadius.circular(20)),
                        child: Text(
                          selected ? 'Sélectionné' : 'Suggéré par l’IA',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(child: Text(template.name, style: headingStyle(fontSize: 17))),
                  Icon(template.theme.format.icon, size: 16, color: goldInk),
                  const SizedBox(width: 4),
                  Text(template.theme.format.label, style: const TextStyle(color: goldInk, fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 3),
              Text(template.description, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: muted, fontSize: 12.5, height: 1.35)),
              if (template.idealFor.isNotEmpty) ...[
                const SizedBox(height: 8),
                Wrap(
                  spacing: 5,
                  runSpacing: 5,
                  children: [
                    for (final item in template.idealFor.take(3))
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: tertiary, borderRadius: BorderRadius.circular(20)),
                        child: Text(item, style: const TextStyle(fontSize: 11, color: muted)),
                      ),
                  ],
                ),
              ],
              if (action != null) ...[const SizedBox(height: 10), action!],
            ],
          ),
        ),
      ),
    );
  }
}

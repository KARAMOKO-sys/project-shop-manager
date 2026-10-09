import 'package:flutter/material.dart';

import '../app/format.dart';
import '../app/theme.dart';
import '../models/models.dart';

class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.compact = false, this.light = false});

  final bool compact;
  final bool light;

  @override
  Widget build(BuildContext context) {
    final size = compact ? 34.0 : 40.0;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            color: light ? Colors.white.withValues(alpha: 0.12) : forest,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(Icons.auto_awesome, color: gold, size: size * 0.52),
        ),
        const SizedBox(width: 10),
        Text.rich(
          TextSpan(
            children: [
              TextSpan(text: 'Shop', style: headingStyle(fontSize: compact ? 20 : 23, color: light ? Colors.white : ink, fontWeight: FontWeight.w700)),
              TextSpan(text: 'AI', style: headingStyle(fontSize: compact ? 20 : 23, color: gold, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ],
    );
  }
}

/// Gold uppercase eyebrow + serif title + short gold rule, like `.section-eyebrow` / `.gold-rule`.
class SectionHeading extends StatelessWidget {
  const SectionHeading({
    required this.title,
    this.eyebrow,
    this.subtitle,
    this.trailing,
    this.color = ink,
    super.key,
  });

  final String title;
  final String? eyebrow;
  final String? subtitle;
  final Widget? trailing;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (eyebrow != null) ...[
                Text(
                  eyebrow!.toUpperCase(),
                  style: const TextStyle(color: goldInk, fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 1.4),
                ),
                const SizedBox(height: 4),
              ],
              Text(title, style: headingStyle(fontSize: 23, color: color)),
              const SizedBox(height: 7),
              Container(width: 36, height: 2.5, decoration: BoxDecoration(color: gold, borderRadius: BorderRadius.circular(2))),
              if (subtitle != null) ...[
                const SizedBox(height: 8),
                Text(subtitle!, style: TextStyle(color: color.withValues(alpha: 0.65), height: 1.4)),
              ],
            ],
          ),
        ),
        ?trailing,
      ],
    );
  }
}

class ProductImage extends StatelessWidget {
  const ProductImage({
    required this.url,
    this.color = forest,
    this.height,
    this.width,
    this.aspectRatio,
    this.icon = Icons.image_outlined,
    super.key,
  });

  final String? url;
  final Color color;
  final double? height;
  final double? width;
  final double? aspectRatio;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      height: height,
      width: width ?? double.infinity,
      color: color.withValues(alpha: 0.08),
      alignment: Alignment.center,
      child: Icon(icon, size: 36, color: color.withValues(alpha: 0.55)),
    );
    final Widget image = url == null
        ? fallback
        : Image.network(
            url!,
            height: height,
            width: width ?? double.infinity,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => fallback,
            loadingBuilder: (context, child, progress) => progress == null
                ? child
                : Container(height: height, width: width ?? double.infinity, color: color.withValues(alpha: 0.05)),
          );
    return aspectRatio == null ? image : AspectRatio(aspectRatio: aspectRatio!, child: image);
  }
}

class ShopLogo extends StatelessWidget {
  const ShopLogo({required this.name, required this.logoUrl, required this.color, this.size = 44, super.key});

  final String name;
  final String? logoUrl;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    final initial = Center(
      child: Text(
        name.isEmpty ? 'B' : name.characters.first.toUpperCase(),
        style: headingStyle(fontSize: size * 0.45, color: contrastingText(color), fontWeight: FontWeight.w700),
      ),
    );
    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(size * 0.3)),
      child: logoUrl == null
          ? initial
          : Image.network(logoUrl!, fit: BoxFit.cover, errorBuilder: (_, _, _) => initial),
    );
  }
}

class PriceTag extends StatelessWidget {
  const PriceTag({
    required this.amount,
    required this.currency,
    this.compareAt,
    this.color,
    this.fontSize = 15,
    super.key,
  });

  final double amount;
  final String currency;
  final double? compareAt;
  final Color? color;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final mainColor = color ?? Theme.of(context).colorScheme.primary;
    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.end,
      spacing: 6,
      children: [
        Text(formatPrice(amount, currency), style: TextStyle(color: mainColor, fontSize: fontSize, fontWeight: FontWeight.w800)),
        if (compareAt != null && compareAt! > amount)
          Text(
            formatPrice(compareAt!, currency),
            style: TextStyle(
              color: muted,
              fontSize: fontSize * 0.78,
              decoration: TextDecoration.lineThrough,
              decorationColor: muted,
            ),
          ),
      ],
    );
  }
}

enum Tone { neutral, success, danger, warning, info, primary, gold }

({Color fg, Color bg}) toneColors(Tone tone) => switch (tone) {
  Tone.neutral => (fg: muted, bg: tertiary),
  Tone.success => (fg: successInk, bg: successSoft),
  Tone.danger => (fg: dangerInk, bg: dangerSoft),
  Tone.warning => (fg: warningInk, bg: warningSoft),
  Tone.info => (fg: info, bg: infoSoft),
  Tone.primary => (fg: forest, bg: forestSoft),
  Tone.gold => (fg: goldInk, bg: goldSoft),
};

class StatusPill extends StatelessWidget {
  const StatusPill({required this.label, this.tone = Tone.neutral, this.icon, super.key});

  final String label;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final colors = toneColors(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: colors.bg, borderRadius: BorderRadius.circular(30)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 13, color: colors.fg), const SizedBox(width: 4)],
          Text(label, style: TextStyle(color: colors.fg, fontSize: 11.5, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

Tone orderStatusTone(OrderStatus status) => switch (status) {
  OrderStatus.received => Tone.neutral,
  OrderStatus.paid => Tone.info,
  OrderStatus.processing => Tone.primary,
  OrderStatus.shipped => Tone.warning,
  OrderStatus.delivered => Tone.success,
  OrderStatus.cancelled => Tone.danger,
};

class OrderStatusPill extends StatelessWidget {
  const OrderStatusPill(this.status, {super.key});

  final OrderStatus status;

  @override
  Widget build(BuildContext context) => StatusPill(label: status.label, tone: orderStatusTone(status));
}

class ModerationBadge extends StatelessWidget {
  const ModerationBadge(this.moderation, {this.showScore = false, super.key});

  final Moderation moderation;
  final bool showScore;

  @override
  Widget build(BuildContext context) {
    final (label, tone, icon) = switch (moderation.status) {
      ModerationStatus.approved => ('Vérifié', Tone.success, Icons.verified_user_outlined),
      ModerationStatus.rejected => ('Refusé', Tone.danger, Icons.gpp_bad_outlined),
      _ => ('En vérification', Tone.warning, Icons.hourglass_top),
    };
    return StatusPill(
      label: showScore ? '$label · risque ${moderation.riskScore}' : label,
      tone: tone,
      icon: icon,
    );
  }
}

class MessageState extends StatelessWidget {
  const MessageState({
    required this.title,
    this.message,
    this.onRetry,
    this.icon = Icons.storefront_outlined,
    this.action,
    super.key,
  });

  final String title;
  final String? message;
  final VoidCallback? onRetry;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 68,
              height: 68,
              decoration: const BoxDecoration(color: goldSoft, shape: BoxShape.circle),
              child: Icon(icon, size: 32, color: goldInk),
            ),
            const SizedBox(height: 16),
            Text(title, textAlign: TextAlign.center, style: headingStyle(fontSize: 20)),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(message!, textAlign: TextAlign.center, style: const TextStyle(color: muted, height: 1.45)),
            ],
            if (onRetry != null) ...[
              const SizedBox(height: 18),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Réessayer')),
            ],
            if (action != null) ...[const SizedBox(height: 18), action!],
          ],
        ),
      ),
    );
  }
}

class LoadingView extends StatelessWidget {
  const LoadingView({this.text, super.key});

  final String? text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5)),
          if (text != null) ...[
            const SizedBox(height: 14),
            Text(text!, style: const TextStyle(color: muted)),
          ],
        ],
      ),
    );
  }
}

class NoticeBanner extends StatelessWidget {
  const NoticeBanner({required this.message, this.tone = Tone.danger, this.icon, super.key});

  final String message;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final colors = toneColors(tone);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: colors.bg, borderRadius: BorderRadius.circular(12)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon ?? (tone == Tone.danger ? Icons.error_outline : Icons.info_outline), size: 19, color: colors.fg),
          const SizedBox(width: 9),
          Expanded(child: Text(message, style: TextStyle(color: colors.fg, height: 1.35))),
        ],
      ),
    );
  }
}

/// Primary button that shows a spinner and disables itself while [loading].
class LoadingButton extends StatelessWidget {
  const LoadingButton({
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.icon,
    this.style,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData? icon;
  final ButtonStyle? style;

  @override
  Widget build(BuildContext context) {
    final foreground = Theme.of(context).colorScheme.onPrimary;
    final Widget child = loading
        ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: foreground))
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[Icon(icon, size: 19), const SizedBox(width: 8)],
              Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
            ],
          );
    return ElevatedButton(onPressed: loading ? null : onPressed, style: style, child: child);
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow({required this.label, required this.value, this.bold = false, this.color, super.key});

  final String label;
  final String value;
  final bool bold;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
      fontSize: bold ? 17 : 14,
      color: color,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style.copyWith(fontWeight: bold ? FontWeight.w800 : FontWeight.w400))),
          Text(value, style: style),
        ],
      ),
    );
  }
}

void showToast(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        backgroundColor: error ? danger : ink,
        content: Row(
          children: [
            Icon(error ? Icons.error_outline : Icons.check_circle_outline, color: error ? Colors.white : gold, size: 20),
            const SizedBox(width: 10),
            Expanded(child: Text(message)),
          ],
        ),
      ),
    );
}

Future<bool> confirmAction(
  BuildContext context, {
  required String title,
  required String message,
  String confirmLabel = 'Supprimer',
  bool destructive = true,
}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title, style: headingStyle(fontSize: 20)),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
        ElevatedButton(
          onPressed: () => Navigator.pop(context, true),
          style: destructive ? ElevatedButton.styleFrom(backgroundColor: danger, foregroundColor: Colors.white) : null,
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return confirmed ?? false;
}

/// Kente / Bogolan inspired tile used at low opacity on hero banners (`--app-motif`).
class MotifPainter extends CustomPainter {
  const MotifPainter({this.color = gold, this.opacity = 0.16});

  final Color color;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = Paint()
      ..color = color.withValues(alpha: opacity)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final fill = Paint()..color = color.withValues(alpha: opacity);
    const tile = 48.0;
    for (var y = 0.0; y < size.height; y += tile) {
      for (var x = 0.0; x < size.width; x += tile) {
        final zigzag = Path()..moveTo(x, y + 10);
        for (var step = 0; step < 8; step++) {
          zigzag.lineTo(x + step * 6 + 6, y + (step.isEven ? 4 : 10));
        }
        canvas.drawPath(zigzag, stroke);
        final diamond = Path()
          ..moveTo(x + 24, y + 18)
          ..lineTo(x + 33, y + 27)
          ..lineTo(x + 24, y + 36)
          ..lineTo(x + 15, y + 27)
          ..close();
        canvas.drawPath(diamond, stroke);
        final core = Path()
          ..moveTo(x + 24, y + 23)
          ..lineTo(x + 28, y + 27)
          ..lineTo(x + 24, y + 31)
          ..lineTo(x + 20, y + 27)
          ..close();
        canvas.drawPath(core, fill);
        canvas.drawCircle(Offset(x + 6, y + 27), 1.6, fill);
        canvas.drawCircle(Offset(x + 42, y + 27), 1.6, fill);
      }
    }
  }

  @override
  bool shouldRepaint(MotifPainter oldDelegate) => oldDelegate.color != color || oldDelegate.opacity != opacity;
}

/// Splits a long form into a titled card.
class FormSection extends StatelessWidget {
  const FormSection({required this.title, required this.children, this.subtitle, this.trailing, super.key});

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: Text(title, style: headingStyle(fontSize: 18))),
                ?trailing,
              ],
            ),
            if (subtitle != null) ...[
              const SizedBox(height: 4),
              Text(subtitle!, style: const TextStyle(color: muted, fontSize: 13, height: 1.35)),
            ],
            const SizedBox(height: 14),
            ...children,
          ],
        ),
      ),
    );
  }
}

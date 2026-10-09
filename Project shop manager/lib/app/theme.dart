import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/models.dart';

/// ShopAI "Afro-chic premium" palette, mirrored from the web `main.css` tokens.
const forest = Color(0xFF0F3D2E);
const forestHover = Color(0xFF0B2E22);
const forestSoft = Color(0xFFE3ECE7);
const gold = Color(0xFFC8963E);
const goldDark = Color(0xFFA87A2A);
const goldInk = Color(0xFF8A6420);
const goldSoft = Color(0xFFF4EAD7);
const ivory = Color(0xFFFAF7F0);
const ink = Color(0xFF1C1B17);
const muted = Color(0xFF5E5A50);
const border = Color(0xFFE8E1D3);
const tertiary = Color(0xFFF4F0E6);

const success = Color(0xFF2E7D4F);
const successSoft = Color(0xFFE2F0E7);
const successInk = Color(0xFF1E5434);
const danger = Color(0xFFB3261E);
const dangerSoft = Color(0xFFF8E4E2);
const dangerInk = Color(0xFF7D1A15);
const warning = Color(0xFFB7791F);
const warningSoft = Color(0xFFFAF0DD);
const warningInk = Color(0xFF6E4812);
const info = Color(0xFF1F6F8B);
const infoSoft = Color(0xFFE0F0F5);

/// Serif display font used for headings on the web (`--app-heading-font`).
TextStyle headingStyle({
  double fontSize = 22,
  Color color = ink,
  FontWeight fontWeight = FontWeight.w600,
  double? height,
}) {
  return GoogleFonts.fraunces(
    fontSize: fontSize,
    color: color,
    fontWeight: fontWeight,
    height: height,
    letterSpacing: -0.2,
  );
}

/// White or near-black text, whichever contrasts best with [background].
Color contrastingText(Color background) =>
    background.computeLuminance() > 0.45 ? ink : Colors.white;

ThemeData buildAppTheme() {
  final base = ThemeData(
    useMaterial3: true,
    scaffoldBackgroundColor: ivory,
    colorScheme: ColorScheme.fromSeed(
      seedColor: forest,
      primary: forest,
      onPrimary: ivory,
      secondary: gold,
      surface: ivory,
      error: danger,
    ),
  );
  return _decorate(
    base,
    primary: forest,
    onPrimary: ivory,
    background: ivory,
    surface: Colors.white,
    text: ink,
    radius: 12,
    bodyFont: 'Inter',
  );
}

/// Theme of a storefront, built from the merchant's settings like `themeVars` on the web.
ThemeData buildShopTheme(BuildContext context, ShopTheme theme) {
  final base = Theme.of(context);
  return _decorate(
    base.copyWith(
      scaffoldBackgroundColor: theme.background,
      colorScheme: base.colorScheme.copyWith(
        primary: theme.primary,
        onPrimary: contrastingText(theme.primary),
        secondary: theme.secondary,
        surface: theme.background,
        onSurface: theme.text,
      ),
    ),
    primary: theme.primary,
    onPrimary: contrastingText(theme.primary),
    background: theme.background,
    surface: theme.surface,
    text: theme.text,
    radius: theme.radius,
    bodyFont: theme.font,
  );
}

ThemeData _decorate(
  ThemeData base, {
  required Color primary,
  required Color onPrimary,
  required Color background,
  required Color surface,
  required Color text,
  required double radius,
  required String bodyFont,
}) {
  final cardRadius = (radius + 4).clamp(0, 28).toDouble();
  final fieldRadius = radius.clamp(0, 18).toDouble();
  TextTheme textTheme;
  try {
    textTheme = GoogleFonts.getTextTheme(bodyFont, base.textTheme);
  } on Exception {
    textTheme = GoogleFonts.interTextTheme(base.textTheme);
  }
  textTheme = textTheme.apply(bodyColor: text, displayColor: text);

  OutlineInputBorder outline(Color color, [double width = 1]) =>
      OutlineInputBorder(
        borderRadius: BorderRadius.circular(fieldRadius),
        borderSide: BorderSide(color: color, width: width),
      );

  return base.copyWith(
    textTheme: textTheme,
    appBarTheme: AppBarTheme(
      backgroundColor: background,
      foregroundColor: text,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      centerTitle: false,
      titleTextStyle: headingStyle(fontSize: 21, color: text),
    ),
    cardTheme: CardThemeData(
      color: surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(cardRadius),
        side: const BorderSide(color: border),
      ),
    ),
    dividerTheme: const DividerThemeData(color: border, space: 1),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: outline(border),
      enabledBorder: outline(border),
      focusedBorder: outline(primary, 1.5),
      errorBorder: outline(danger),
      focusedErrorBorder: outline(danger, 1.5),
      labelStyle: const TextStyle(color: muted),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: onPrimary,
        disabledBackgroundColor: primary.withValues(alpha: 0.4),
        disabledForegroundColor: onPrimary.withValues(alpha: 0.8),
        elevation: 0,
        minimumSize: const Size(48, 50),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        shape: const StadiumBorder(),
        textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: primary,
        minimumSize: const Size(44, 46),
        side: BorderSide(color: primary.withValues(alpha: 0.35)),
        shape: const StadiumBorder(),
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: primary,
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
      ),
    ),
    chipTheme: base.chipTheme.copyWith(
      backgroundColor: Colors.white,
      selectedColor: primary,
      side: const BorderSide(color: border),
      shape: const StadiumBorder(),
      labelStyle: TextStyle(color: text, fontWeight: FontWeight.w600),
      secondaryLabelStyle: TextStyle(color: onPrimary),
      checkmarkColor: onPrimary,
    ),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: primary),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: ink,
      contentTextStyle: textTheme.bodyMedium?.copyWith(color: Colors.white),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: forestSoft,
      surfaceTintColor: Colors.transparent,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 11.5,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected) ? forest : muted,
        ),
      ),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: primary,
      unselectedLabelColor: muted,
      indicatorColor: gold,
      dividerColor: border,
      tabAlignment: TabAlignment.start,
      labelStyle: const TextStyle(fontWeight: FontWeight.w700),
    ),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith(
        (states) => states.contains(WidgetState.selected) ? Colors.white : null,
      ),
      trackColor: WidgetStateProperty.resolveWith(
        (states) => states.contains(WidgetState.selected) ? primary : null,
      ),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: background,
      surfaceTintColor: Colors.transparent,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: background,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
  );
}

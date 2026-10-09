/// French formatting helpers mirroring `frontend/app/utils/format.ts`.
library;

const _cfaCurrencies = {'XOF', 'XAF'};
const _symbols = {'EUR': '€', 'USD': r'$US', 'MAD': 'MAD', 'GNF': 'GNF', 'CDF': 'CDF'};
const _months = [
  'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
  'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
];

const currencies = {
  'XOF': 'Franc CFA (BCEAO) — XOF',
  'XAF': 'Franc CFA (BEAC) — XAF',
  'GNF': 'Franc guinéen — GNF',
  'MAD': 'Dirham marocain — MAD',
  'CDF': 'Franc congolais — CDF',
  'EUR': 'Euro — EUR',
  'USD': 'Dollar US — USD',
};

/// "12 500" with a narrow no-break space as thousands separator, comma decimals.
String formatNumber(num value, [int fractionDigits = 0]) {
  final negative = value < 0;
  final fixed = value.abs().toStringAsFixed(fractionDigits);
  final parts = fixed.split('.');
  final grouped = parts[0].replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ' ');
  var decimals = parts.length > 1 ? parts[1].replaceFirst(RegExp(r'0+$'), '') : '';
  if (decimals.isNotEmpty) decimals = ',$decimals';
  return '${negative ? '−' : ''}$grouped$decimals';
}

/// "4 500 FCFA" for CFA francs, "12,5 €" for euros, "300 GNF" otherwise.
String formatPrice(num amount, [String currency = 'XOF']) {
  final code = currency.toUpperCase();
  if (_cfaCurrencies.contains(code)) return '${formatNumber(amount)} FCFA';
  return '${formatNumber(amount, 2)} ${_symbols[code] ?? code}';
}

/// Short unit shown next to amount inputs.
String currencyUnit(String currency) =>
    _cfaCurrencies.contains(currency.toUpperCase()) ? 'FCFA' : currency.toUpperCase();

String _two(int value) => value.toString().padLeft(2, '0');

/// "7 oct. 2026 à 14:05".
String formatDate(DateTime? date) {
  if (date == null) return '—';
  return '${formatShortDate(date)} à ${_two(date.hour)}:${_two(date.minute)}';
}

/// "7 oct. 2026".
String formatShortDate(DateTime? date) {
  if (date == null) return '—';
  return '${date.day} ${_months[date.month - 1]} ${date.year}';
}

/// "2026-10-07" as expected by the API date fields.
String isoDate(DateTime date) => '${date.year}-${_two(date.month)}-${_two(date.day)}';

String plural(int count, String singular, [String? pluralForm]) =>
    '${formatNumber(count)} ${count > 1 ? (pluralForm ?? '${singular}s') : singular}';

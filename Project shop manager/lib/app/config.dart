/// Base URL of the Laravel API (`e-shop-full`), e.g.
/// `flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1`.
/// The default targets `php artisan serve` from the Android emulator.
const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);

const _loopbackHosts = {'localhost', '127.0.0.1', '0.0.0.0'};

/// Laravel builds storage URLs from APP_URL (often `http://localhost:8000`), which a phone
/// cannot reach. Such URLs are rewritten to the host the app already uses for the API.
String? resolveMediaUrl(String? url, {String baseUrl = apiBaseUrl}) {
  if (url == null || url.trim().isEmpty) return null;
  final media = Uri.tryParse(url.trim());
  if (media == null) return null;
  final api = Uri.parse(baseUrl);
  if (!media.hasScheme) {
    return api.replace(path: media.path, query: media.query).toString();
  }
  if (_loopbackHosts.contains(media.host) && !_loopbackHosts.contains(api.host)) {
    return media
        .replace(scheme: api.scheme, host: api.host, port: api.port)
        .toString();
  }
  return media.toString();
}

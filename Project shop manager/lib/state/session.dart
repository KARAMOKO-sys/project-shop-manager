import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';
import '../services/api_service.dart';

/// Signed-in merchant: Sanctum token persisted on the device, like the web `shopai_token` cookie.
class Session extends ChangeNotifier {
  Session._(this._api, this._preferences, this._user) {
    _api.onUnauthorized = _expire;
  }

  static const _tokenKey = 'shopai_token';
  static const _userKey = 'shopai_user';

  final ApiService _api;
  final SharedPreferences _preferences;
  User? _user;

  User? get user => _user;
  bool get isAuthenticated => _api.token != null;
  bool get isAdmin => _user?.isAdmin ?? false;

  static Future<Session> restore(ApiService api, SharedPreferences preferences) async {
    api.token = preferences.getString(_tokenKey);
    User? user;
    final rawUser = preferences.getString(_userKey);
    if (api.token != null && rawUser != null) {
      try {
        final json = jsonDecode(rawUser);
        if (json is Map<String, dynamic>) user = User.fromJson(json);
      } on FormatException {
        user = null;
      }
    }
    final session = Session._(api, preferences, user);
    if (api.token != null) unawaited(session.refreshUser());
    return session;
  }

  /// Re-reads the profile (admin flag may change). A 401 clears the session via [_expire].
  Future<void> refreshUser() async {
    try {
      final user = await _api.me();
      await _store(user, _api.token!);
    } on ApiException {
      // Offline or expired: the cached profile stays until the API answers 401.
    }
  }

  Future<void> login(String email, String password) async {
    final (user, token) = await _api.login(email.trim(), password);
    await _store(user, token);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final (user, token) = await _api.register(
      name: name.trim(),
      email: email.trim(),
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    await _store(user, token);
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } on ApiException {
      // The token may already be revoked: the local session is cleared regardless.
    }
    await _clear();
  }

  Future<void> _store(User user, String token) async {
    _api.token = token;
    _user = user;
    await _preferences.setString(_tokenKey, token);
    await _preferences.setString(_userKey, jsonEncode(user.toJson()));
    notifyListeners();
  }

  void _expire() => unawaited(_clear());

  Future<void> _clear() async {
    if (_api.token == null && _user == null) return;
    _api.token = null;
    _user = null;
    await _preferences.remove(_tokenKey);
    await _preferences.remove(_userKey);
    notifyListeners();
  }
}

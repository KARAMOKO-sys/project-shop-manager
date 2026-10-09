import 'package:flutter/material.dart';

import '../../app/scope.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import 'login_screen.dart';

/// Merchant sign-up, mirroring the web `pages/inscription.vue`.
///
/// Pops with `true` once the account is created and the session is open.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _minPasswordLength = 8;

  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  Map<String, String> _errors = const {};
  String? _generalError;
  bool _obscure = true;
  bool _submitting = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  /// Same rules as the API, checked locally to spare a round trip.
  Map<String, String> _localErrors() {
    final errors = <String, String>{};
    if (_password.text.length < _minPasswordLength) {
      errors['password'] =
          'Le mot de passe doit contenir au moins $_minPasswordLength caractères.';
    } else if (_password.text != _confirmation.text) {
      errors['password_confirmation'] = 'Les mots de passe ne correspondent pas.';
    }
    return errors;
  }

  Future<void> _submit() async {
    if (_submitting) return;
    FocusScope.of(context).unfocus();
    final localErrors = _localErrors();
    if (localErrors.isNotEmpty && _name.text.trim().isNotEmpty && _email.text.trim().isNotEmpty) {
      setState(() {
        _errors = localErrors;
        _generalError = null;
      });
      return;
    }
    setState(() {
      _submitting = true;
      _errors = const {};
      _generalError = null;
    });
    try {
      await context.session.register(
        name: _name.text,
        email: _email.text,
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      );
      if (!mounted) return;
      showToast(context, 'Bienvenue sur ShopAI ! Créons votre boutique.');
      Navigator.pop(context, true);
    } on Object catch (error) {
      if (!mounted) return;
      final fields = error is ApiException ? error.fieldErrors : const <String, String>{};
      setState(() {
        _errors = fields;
        _generalError = fields.isEmpty ? errorText(error) : null;
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _openLogin() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute<bool>(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final toggle = PasswordToggle(
      obscured: _obscure,
      onPressed: () => setState(() => _obscure = !_obscure),
    );
    return AuthLayout(
      title: 'Créer un compte',
      subtitle: 'Gratuit, sans engagement. Votre boutique est à 5 minutes.',
      footer: AuthSwitchLink(
        question: 'Déjà inscrit ?',
        action: 'Se connecter',
        onPressed: _submitting ? null : _openLogin,
      ),
      children: [
        if (_generalError != null) ...[
          NoticeBanner(message: _generalError!),
          const SizedBox(height: 16),
        ],
        AutofillGroup(
          child: Column(
            children: [
              TextField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.name],
                decoration: InputDecoration(
                  labelText: 'Nom complet',
                  prefixIcon: const Icon(Icons.person_outline),
                  errorText: _errors['name'],
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                autocorrect: false,
                autofillHints: const [AutofillHints.email],
                decoration: InputDecoration(
                  labelText: 'Adresse e-mail',
                  prefixIcon: const Icon(Icons.mail_outline),
                  errorText: _errors['email'],
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _password,
                obscureText: _obscure,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.newPassword],
                decoration: InputDecoration(
                  labelText: 'Mot de passe',
                  helperText: '$_minPasswordLength caractères minimum',
                  prefixIcon: const Icon(Icons.lock_outline),
                  errorText: _errors['password'],
                  suffixIcon: toggle,
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _confirmation,
                obscureText: _obscure,
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.newPassword],
                onSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: 'Confirmez le mot de passe',
                  prefixIcon: const Icon(Icons.lock_reset),
                  errorText: _errors['password_confirmation'],
                  suffixIcon: toggle,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        LoadingButton(
          label: 'Créer mon compte',
          icon: Icons.person_add_alt_1,
          loading: _submitting,
          onPressed: _submit,
        ),
      ],
    );
  }
}

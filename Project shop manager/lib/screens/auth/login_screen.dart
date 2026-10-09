import 'package:flutter/material.dart';

import '../../app/scope.dart';
import '../../app/theme.dart';
import '../../services/api_service.dart';
import '../../widgets/common.dart';
import 'register_screen.dart';

/// Merchant sign-in, mirroring the web `pages/login.vue`.
///
/// Pops with `true` once the session is open.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  Map<String, String> _errors = const {};
  String? _generalError;
  bool _obscure = true;
  bool _submitting = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_submitting) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _errors = const {};
      _generalError = null;
    });
    final session = context.session;
    try {
      await session.login(_email.text, _password.text);
      if (!mounted) return;
      final name = session.user?.name;
      showToast(context, name == null || name.isEmpty ? 'Bon retour !' : 'Bon retour, $name !');
      Navigator.pop(context, true);
    } on Object catch (error) {
      if (!mounted) return;
      final fields = error is ApiException ? error.fieldErrors : const <String, String>{};
      setState(() {
        _errors = fields;
        _generalError = fields.isEmpty ? errorText(error, 'Identifiants incorrects.') : null;
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _openRegister() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute<bool>(builder: (_) => const RegisterScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AuthLayout(
      title: 'Connexion',
      subtitle: 'Ravi de vous revoir ! Accédez à votre espace marchand.',
      footer: AuthSwitchLink(
        question: 'Pas encore de compte ?',
        action: 'Créer un compte',
        onPressed: _submitting ? null : _openRegister,
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
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.password],
                onSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: 'Mot de passe',
                  prefixIcon: const Icon(Icons.lock_outline),
                  errorText: _errors['password'],
                  suffixIcon: PasswordToggle(
                    obscured: _obscure,
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        LoadingButton(
          label: 'Se connecter',
          icon: Icons.login,
          loading: _submitting,
          onPressed: _submit,
        ),
      ],
    );
  }
}

/// Shared frame of the sign-in and sign-up screens: forest hero with the brand, then a form card.
class AuthLayout extends StatelessWidget {
  const AuthLayout({
    required this.title,
    required this.subtitle,
    required this.children,
    this.footer,
    super.key,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.paddingOf(context).top;
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        scrolledUnderElevation: 0,
      ),
      body: SingleChildScrollView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              clipBehavior: Clip.antiAlias,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [forest, forestHover],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Stack(
                children: [
                  const Positioned.fill(child: CustomPaint(painter: MotifPainter(opacity: 0.12))),
                  Padding(
                    padding: EdgeInsets.fromLTRB(24, topInset + kToolbarHeight, 24, 56),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const BrandMark(light: true),
                        const SizedBox(height: 22),
                        Text(
                          'ESPACE MARCHAND',
                          style: TextStyle(
                            color: gold.withValues(alpha: 0.95),
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.6,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          title,
                          style: headingStyle(fontSize: 32, color: Colors.white, height: 1.1),
                        ),
                        const SizedBox(height: 8),
                        Container(
                          width: 36,
                          height: 2.5,
                          decoration: BoxDecoration(
                            color: gold,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          subtitle,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.82),
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Transform.translate(
              offset: const Offset(0, -32),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 480),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            ...children,
                            if (footer != null) ...[const SizedBox(height: 18), footer!],
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            SizedBox(height: MediaQuery.paddingOf(context).bottom),
          ],
        ),
      ),
    );
  }
}

/// "Pas encore de compte ? Créer un compte" line under the auth forms.
class AuthSwitchLink extends StatelessWidget {
  const AuthSwitchLink({
    required this.question,
    required this.action,
    required this.onPressed,
    super.key,
  });

  final String question;
  final String action;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(question, style: const TextStyle(color: muted)),
        TextButton(onPressed: onPressed, child: Text(action)),
      ],
    );
  }
}

/// Eye icon switching a password field between hidden and visible.
class PasswordToggle extends StatelessWidget {
  const PasswordToggle({required this.obscured, required this.onPressed, super.key});

  final bool obscured;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onPressed,
      tooltip: obscured ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
      icon: Icon(obscured ? Icons.visibility_outlined : Icons.visibility_off_outlined),
    );
  }
}

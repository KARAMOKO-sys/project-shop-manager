import 'package:flutter/material.dart';

import '../app/theme.dart';
import '../models/models.dart';
import '../widgets/common.dart';

typedef ChatSender = Future<ChatReply> Function(String message, List<ChatTurn> history);

/// Conversation with the platform assistant (`POST /chat`) or a shop bot
/// (`POST /storefront/{slug}/chat`), mirroring the web `ChatWidget`.
class ChatScreen extends StatefulWidget {
  const ChatScreen({
    required this.send,
    required this.title,
    required this.welcome,
    this.subtitle = 'Répond en quelques secondes, 24h/24',
    this.suggestions = const [],
    this.accentColor = forest,
    this.onProductTap,
    super.key,
  });

  final ChatSender send;
  final String title;
  final String subtitle;
  final String welcome;
  final List<String> suggestions;
  final Color accentColor;
  final ValueChanged<Product>? onProductTap;

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _draft = TextEditingController();
  final _scroller = ScrollController();
  late final List<ChatTurn> _messages = [ChatTurn(role: 'assistant', content: widget.welcome)];
  late List<String> _suggestions = [...widget.suggestions];
  bool _sending = false;

  @override
  void dispose() {
    _draft.dispose();
    _scroller.dispose();
    super.dispose();
  }

  Future<void> _send(String text) async {
    final message = text.trim();
    if (message.isEmpty || _sending) return;
    final history = _messages.skip(1).toList();
    setState(() {
      _messages.add(ChatTurn(role: 'user', content: message));
      _draft.clear();
      _suggestions = [];
      _sending = true;
    });
    _scrollToBottom();
    try {
      final reply = await widget.send(message, history);
      if (!mounted) return;
      setState(() {
        _messages.add(ChatTurn(role: 'assistant', content: reply.reply, products: reply.products));
        _suggestions = reply.suggestions;
      });
    } on Object {
      if (!mounted) return;
      setState(() {
        _messages.add(const ChatTurn(
          role: 'assistant',
          content: 'Désolé, je ne peux pas répondre pour le moment. Réessayez dans un instant.',
        ));
      });
    } finally {
      if (mounted) setState(() => _sending = false);
      _scrollToBottom();
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroller.hasClients) {
        _scroller.animateTo(
          _scroller.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final accent = widget.accentColor;
    final onAccent = contrastingText(accent);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: accent,
        foregroundColor: onAccent,
        titleSpacing: 0,
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: onAccent.withValues(alpha: 0.14),
              child: const Icon(Icons.auto_awesome, color: gold, size: 19),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: headingStyle(fontSize: 17, color: onAccent)),
                  Text(widget.subtitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: onAccent.withValues(alpha: 0.75))),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scroller,
              padding: const EdgeInsets.fromLTRB(14, 16, 14, 8),
              itemCount: _messages.length + (_sending ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == _messages.length) return _typing();
                return _bubble(_messages[index]);
              },
            ),
          ),
          if (_suggestions.isNotEmpty && !_sending)
            SizedBox(
              height: 46,
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                scrollDirection: Axis.horizontal,
                itemCount: _suggestions.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, index) => ActionChip(
                  label: Text(_suggestions[index], style: TextStyle(color: accent, fontSize: 12.5)),
                  side: BorderSide(color: accent.withValues(alpha: 0.35)),
                  backgroundColor: Colors.white,
                  onPressed: () => _send(_suggestions[index]),
                ),
              ),
            ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _draft,
                      maxLength: 1000,
                      minLines: 1,
                      maxLines: 4,
                      textCapitalization: TextCapitalization.sentences,
                      textInputAction: TextInputAction.send,
                      onSubmitted: _send,
                      decoration: const InputDecoration(hintText: 'Écrivez votre message…', counterText: ''),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    tooltip: 'Envoyer',
                    onPressed: _sending ? null : () => _send(_draft.text),
                    style: IconButton.styleFrom(backgroundColor: accent, foregroundColor: onAccent, minimumSize: const Size(48, 48)),
                    icon: const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bubble(ChatTurn turn) {
    final accent = widget.accentColor;
    final isUser = turn.isUser;
    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.82),
        child: Column(
          crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isUser ? accent : Colors.white,
                border: isUser ? null : Border.all(color: border),
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(18),
                  topRight: const Radius.circular(18),
                  bottomLeft: Radius.circular(isUser ? 18 : 4),
                  bottomRight: Radius.circular(isUser ? 4 : 18),
                ),
              ),
              child: Text(
                turn.content,
                style: TextStyle(color: isUser ? contrastingText(accent) : ink, height: 1.4),
              ),
            ),
            for (final product in turn.products)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Material(
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                    side: const BorderSide(color: border),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: widget.onProductTap == null ? null : () => widget.onProductTap!(product),
                    child: Padding(
                      padding: const EdgeInsets.all(8),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(9),
                            child: ProductImage(url: product.imageUrl, color: accent, width: 46, height: 46),
                          ),
                          const SizedBox(width: 10),
                          Flexible(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                                PriceTag(amount: product.price, currency: product.currency, color: accent, fontSize: 13),
                              ],
                            ),
                          ),
                          if (widget.onProductTap != null) const Icon(Icons.chevron_right, color: muted),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _typing() => Align(
    alignment: Alignment.centerLeft,
    child: Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: widget.accentColor)),
          const SizedBox(width: 10),
          const Text('L’assistant écrit…', style: TextStyle(color: muted)),
        ],
      ),
    ),
  );
}

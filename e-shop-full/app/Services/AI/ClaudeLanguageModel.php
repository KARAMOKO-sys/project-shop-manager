<?php

namespace App\Services\AI;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Illuminate\Support\Facades\Log;

class ClaudeLanguageModel implements LanguageModel
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private ?Client $client = null;

    public function __construct(
        private ?string $apiKey,
        private string $model,
        private int $timeout,
    ) {}

    public function isAvailable(): bool
    {
        return filled($this->apiKey);
    }

    public function name(): string
    {
        return 'claude';
    }

    public function structured(string $system, string $prompt, array $schema, string $effort = 'low'): ?array
    {
        $message = $this->send(
            system: $system,
            messages: [['role' => 'user', 'content' => $prompt]],
            maxTokens: 16000,
            outputConfig: [
                'effort' => $effort,
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
        );

        $text = $message ? $this->firstText($message) : null;

        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function chat(string $system, array $messages): ?string
    {
        $message = $this->send(
            system: $system,
            messages: $messages,
            maxTokens: 4000,
            outputConfig: ['effort' => 'low'],
        );

        return $message ? $this->firstText($message) : null;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $outputConfig
     */
    private function send(string $system, array $messages, int $maxTokens, array $outputConfig): ?BetaMessage
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: $maxTokens,
                messages: $messages,
                model: $this->model,
                system: $system,
                outputConfig: $outputConfig,
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
                requestOptions: ['timeout' => (float) $this->timeout],
            );
        } catch (AnthropicException $exception) {
            Log::warning('Claude request failed, falling back to the local engine.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            Log::info('Claude declined the request.', ['category' => $message->stopDetails?->category]);

            return null;
        }

        return $message;
    }

    private function firstText(BetaMessage $message): ?string
    {
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return null;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}

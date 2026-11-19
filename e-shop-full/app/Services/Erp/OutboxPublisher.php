<?php

namespace App\Services\Erp;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Relays pending outbox messages to the ESB endpoint, signed with HMAC-SHA256.
 * Delivery is "at least once": the ESB deduplicates on the message UUID
 * (X-ShopAI-Event-Id header). Failures are retried with exponential backoff.
 */
class OutboxPublisher
{
    /**
     * @return array{published: int, failed: int}
     */
    public function publishPending(): array
    {
        $config = config('erp.esb');
        $result = ['published' => 0, 'failed' => 0];

        if (blank($config['endpoint']) || blank($config['secret'])) {
            return $result;
        }

        $messages = OutboxMessage::query()
            ->where('status', OutboxMessage::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($config['batch_size'])
            ->get();

        foreach ($messages as $message) {
            $this->publish($message, $config) ? $result['published']++ : $result['failed']++;
        }

        return $result;
    }

    /**
     * @param  array{endpoint: string, secret: string, timeout: int, max_attempts: int}  $config
     */
    private function publish(OutboxMessage $message, array $config): bool
    {
        $body = json_encode([
            'id' => $message->uuid,
            'event' => $message->event,
            'shop_id' => $message->shop_id,
            'occurred_at' => $message->created_at?->toIso8601String(),
            'data' => $message->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $config['secret']);

        try {
            $response = Http::timeout($config['timeout'])
                ->withHeaders([
                    'X-ShopAI-Event' => $message->event,
                    'X-ShopAI-Event-Id' => $message->uuid,
                    'X-ShopAI-Timestamp' => $timestamp,
                    'X-ShopAI-Signature' => "sha256={$signature}",
                ])
                ->withBody($body, 'application/json')
                ->post($config['endpoint']);

            if ($response->successful()) {
                $message->update(['status' => OutboxMessage::STATUS_PUBLISHED, 'published_at' => now(), 'attempts' => $message->attempts + 1, 'last_error' => null]);

                return true;
            }

            $error = "HTTP {$response->status()}";
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $attempts = $message->attempts + 1;

        $message->update([
            'attempts' => $attempts,
            'last_error' => mb_substr($error, 0, 1000),
            'status' => $attempts >= $config['max_attempts'] ? OutboxMessage::STATUS_FAILED : OutboxMessage::STATUS_PENDING,
            'available_at' => now()->addSeconds(min(3600, 2 ** $attempts * 15)),
        ]);

        return false;
    }
}

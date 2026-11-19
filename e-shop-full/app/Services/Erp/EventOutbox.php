<?php

namespace App\Services\Erp;

use App\Models\OutboxMessage;
use App\Models\Shop;
use Illuminate\Support\Str;

/**
 * Transactional outbox: business events are stored with the change that caused
 * them, then relayed to the ESB by `erp:outbox:publish`. A rollback of the
 * business transaction also removes the event, so the bus never hears about
 * something that did not happen.
 */
class EventOutbox
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $event, array $payload, ?Shop $shop = null): OutboxMessage
    {
        return OutboxMessage::create([
            'uuid' => (string) Str::uuid(),
            'shop_id' => $shop?->id,
            'event' => $event,
            'payload' => $payload,
            'status' => OutboxMessage::STATUS_PENDING,
            'available_at' => now(),
        ]);
    }
}

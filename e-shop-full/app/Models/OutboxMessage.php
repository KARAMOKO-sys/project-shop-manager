<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Business event waiting to be relayed to the Enterprise Service Bus.
 */
class OutboxMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['uuid', 'shop_id', 'event', 'payload', 'status', 'attempts', 'available_at', 'published_at', 'last_error'];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

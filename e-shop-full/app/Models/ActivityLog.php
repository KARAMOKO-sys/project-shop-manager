<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Business audit trail of a shop ("chatter"): who did what, on which record.
 */
class ActivityLog extends Model
{
    protected $fillable = ['shop_id', 'user_id', 'subject_type', 'subject_id', 'event', 'description', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

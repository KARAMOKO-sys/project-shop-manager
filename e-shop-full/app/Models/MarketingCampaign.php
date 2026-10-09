<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    protected $fillable = ['shop_id', 'name', 'channel', 'template', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

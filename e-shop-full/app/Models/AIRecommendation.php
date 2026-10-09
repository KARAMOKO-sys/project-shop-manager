<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRecommendation extends Model
{
    protected $fillable = ['shop_id', 'product_id', 'recommended_product_id', 'score', 'reason'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function recommendedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'recommended_product_id');
    }
}

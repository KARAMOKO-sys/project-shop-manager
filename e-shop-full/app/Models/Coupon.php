<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    protected $fillable = ['shop_id', 'code', 'type', 'value', 'min_order_amount', 'starts_at', 'ends_at', 'usage_limit', 'times_used', 'status'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'times_used' => 0,
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * A date without a time means the coupon stays valid until the end of that day.
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
                ? Carbon::parse($value)->endOfDay()
                : $value,
        );
    }

    public function isRedeemableFor(float $subtotal): bool
    {
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture())
            && ($this->usage_limit === 0 || $this->times_used < $this->usage_limit)
            && $subtotal >= (float) $this->min_order_amount;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percentage'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }
}

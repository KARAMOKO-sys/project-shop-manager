<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_order_amount' => (float) $this->min_order_amount,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'usage_limit' => $this->usage_limit,
            'times_used' => $this->times_used,
            'status' => $this->status,
            'is_redeemable' => $this->isRedeemableFor((float) $this->min_order_amount),
        ];
    }
}

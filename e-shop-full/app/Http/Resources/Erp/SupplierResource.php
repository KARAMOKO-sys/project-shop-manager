<?php

namespace App\Http\Resources\Erp;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'payment_terms_days' => $this->payment_terms_days,
            'lead_time_days' => $this->lead_time_days,
            'notes' => $this->notes,
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
        ];
    }
}

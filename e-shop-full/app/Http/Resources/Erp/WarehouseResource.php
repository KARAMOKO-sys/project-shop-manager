<?php

namespace App\Http\Resources\Erp;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
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
            'code' => $this->code,
            'address' => $this->address,
            'is_default' => $this->is_default,
            'products_count' => $this->whenCounted('stocks'),
            'stock_quantity' => $this->whenAggregated('stocks', 'quantity', 'sum', fn ($value) => (int) $value),
        ];
    }
}

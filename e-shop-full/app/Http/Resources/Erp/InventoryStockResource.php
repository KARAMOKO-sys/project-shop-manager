<?php

namespace App\Http\Resources\Erp;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stock position of a product: total, valuation and split per warehouse.
 *
 * @mixin Product
 */
class InventoryStockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $costPrice = $this->cost_price !== null ? (float) $this->cost_price : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'image_url' => $this->image_url,
            'stock_quantity' => $this->stock_quantity,
            'reorder_point' => $this->reorder_point,
            'cost_price' => $costPrice,
            'stock_value' => round($this->stock_quantity * ($costPrice ?? 0), 2),
            'stock_status' => $this->stock_status,
            'warehouses' => $this->whenLoaded('stocks', fn () => $this->stocks->map(fn (ProductStock $stock): array => [
                'warehouse_id' => $stock->warehouse_id,
                'code' => $stock->warehouse->code,
                'name' => $stock->warehouse->name,
                'quantity' => $stock->quantity,
            ])->values()),
        ];
    }
}

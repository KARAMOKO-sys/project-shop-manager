<?php

namespace App\Http\Resources\Erp;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
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
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name, 'code' => $this->warehouse->code]),
            'expected_at' => $this->expected_at?->toDateString(),
            'confirmed_at' => $this->confirmed_at,
            'received_at' => $this->received_at,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'is_late' => $this->status->canReceive() && $this->expected_at !== null && $this->expected_at->lt(today()),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (PurchaseOrderLine $line): array => [
                'id' => $line->id,
                'product' => ['id' => $line->product->id, 'name' => $line->product->name, 'sku' => $line->product->sku],
                'quantity' => $line->quantity,
                'quantity_received' => $line->quantity_received,
                'remaining_quantity' => $line->remainingQuantity(),
                'unit_cost' => (float) $line->unit_cost,
                'total' => (float) $line->total,
            ])->values()),
            'lines_count' => $this->whenCounted('lines'),
            'created_at' => $this->created_at,
        ];
    }
}

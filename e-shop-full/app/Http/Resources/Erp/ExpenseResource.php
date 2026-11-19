<?php

namespace App\Http\Resources\Erp;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'category' => $this->category,
            'category_label' => $this->category->label(),
            'label' => $this->label,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'spent_at' => $this->spent_at?->toDateString(),
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null, null),
        ];
    }
}

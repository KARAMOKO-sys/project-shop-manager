<?php

namespace App\Http\Resources\Erp;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'order' => $this->whenLoaded('order', fn () => $this->order ? ['id' => $this->order->id, 'order_number' => $this->order->order_number] : null, null),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'amount_due' => $this->amountDue(),
            'currency' => $this->currency,
            'notes' => $this->notes,
            'is_overdue' => $this->isOverdue(),
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'total' => (float) $line->total,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (InvoicePayment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->toDateString(),
                'reference' => $payment->reference,
            ])),
        ];
    }
}

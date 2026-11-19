<?php

namespace App\Services\Erp;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoicing application. Line prices are tax-included (as displayed in the
 * shop); the tax is extracted per line with the line's rate.
 */
class InvoiceService
{
    public function __construct(
        private DocumentNumberGenerator $numbers,
        private ActivityLogger $activity,
        private EventOutbox $outbox,
    ) {}

    /**
     * @param  array{customer_id?: ?int, customer_name: string, customer_email?: ?string, issue_date?: ?string, due_date?: ?string, notes?: ?string, lines: list<array{product_id?: ?int, description: string, quantity: float|int|string, unit_price: float|int|string, tax_rate?: float|int|string|null}>}  $data
     */
    public function create(Shop $shop, array $data): Invoice
    {
        return DB::transaction(function () use ($shop, $data): Invoice {
            $issueDate = $data['issue_date'] ?? now()->toDateString();

            $invoice = $shop->invoices()->create([
                'customer_id' => $data['customer_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'status' => InvoiceStatus::Draft,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'issue_date' => $issueDate,
                'due_date' => $data['due_date'] ?? $issueDate,
                'currency' => $shop->currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceLines($invoice, $data['lines'], (float) $shop->tax_rate);
            $this->activity->log($shop, 'invoice.created', 'Facture brouillon créée pour '.$invoice->customer_name.'.', $invoice);

            return $invoice->load('lines', 'payments');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Une facture validée ne peut plus être modifiée : annulez-la et créez-en une nouvelle.']);
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $invoice->update(collect($data)->except('lines')->all());

            if (isset($data['lines'])) {
                $this->replaceLines($invoice, $data['lines'], (float) $invoice->shop->tax_rate);
            }

            return $invoice->load('lines', 'payments');
        });
    }

    /**
     * Draft invoice copying an order: its items, delivery fee and discount.
     */
    public function createFromOrder(Order $order): Invoice
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['order_id' => 'Une commande annulée ne peut pas être facturée.']);
        }

        if (Invoice::query()->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['order_id' => "La commande {$order->order_number} est déjà facturée."]);
        }

        $order->loadMissing('items', 'customer', 'shop');
        $taxRate = (float) $order->shop->tax_rate;

        $lines = $order->items->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'description' => $item->product_name.($item->variant_label ? " ({$item->variant_label})" : ''),
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_rate' => $taxRate,
        ])->all();

        if ((float) $order->shipping_amount > 0) {
            $lines[] = ['description' => 'Frais de livraison', 'quantity' => 1, 'unit_price' => (float) $order->shipping_amount, 'tax_rate' => $taxRate];
        }

        if ((float) $order->discount_amount > 0) {
            $lines[] = ['description' => 'Remise', 'quantity' => 1, 'unit_price' => -(float) $order->discount_amount, 'tax_rate' => $taxRate];
        }

        return $this->create($order->shop, [
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'customer_name' => trim($order->customer->first_name.' '.$order->customer->last_name),
            'customer_email' => $order->customer->email,
            'notes' => "Commande {$order->order_number}",
            'lines' => $lines,
        ]);
    }

    /**
     * Validate the invoice: it receives its definitive, gap-free number.
     */
    public function post(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Cette facture est déjà validée.']);
        }

        if ($invoice->lines()->doesntExist()) {
            throw ValidationException::withMessages(['lines' => 'Une facture doit contenir au moins une ligne.']);
        }

        DB::transaction(function () use ($invoice): void {
            $year = $invoice->issue_date->format('Y');

            $invoice->update([
                'number' => $this->numbers->next($invoice->shop, "invoice_{$year}", "FAC-{$year}-"),
                'status' => InvoiceStatus::Posted,
                'posted_at' => now(),
            ]);

            $this->activity->log($invoice->shop, 'invoice.posted', "Facture {$invoice->number} validée.", $invoice);
            $this->outbox->record('invoice.posted', $this->eventPayload($invoice), $invoice->shop);
        });

        return $invoice->load('lines', 'payments');
    }

    /**
     * @param  array{amount: float|int|string, method: string, paid_at?: ?string, reference?: ?string}  $data
     */
    public function registerPayment(Invoice $invoice, array $data, ?User $user = null): Invoice
    {
        if (! $invoice->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Seule une facture validée et non soldée peut recevoir un paiement.']);
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount > $invoice->amountDue()) {
            throw ValidationException::withMessages(['amount' => 'Le montant dépasse le reste à payer ('.number_format($invoice->amountDue(), 0, ',', ' ')." {$invoice->currency})."]);
        }

        DB::transaction(function () use ($invoice, $data, $amount, $user): void {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'user_id' => $user?->id,
            ]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            $isPaid = $paid >= (float) $invoice->total;

            $invoice->update([
                'amount_paid' => $paid,
                'status' => $isPaid ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid,
            ]);

            $this->activity->log($invoice->shop, 'invoice.payment', 'Paiement de '.number_format($amount, 0, ',', ' ')." {$invoice->currency} enregistré sur {$invoice->number}.", $invoice, ['amount' => $amount, 'method' => $data['method']]);

            if ($isPaid) {
                $this->outbox->record('invoice.paid', $this->eventPayload($invoice), $invoice->shop);
            }
        });

        return $invoice->load('lines', 'payments');
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ((float) $invoice->amount_paid > 0 || $invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Une facture ayant reçu un paiement ne peut pas être annulée.']);
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled]);
        $this->activity->log($invoice->shop, 'invoice.cancelled', 'Facture '.($invoice->number ?? 'brouillon').' annulée.', $invoice);

        return $invoice->load('lines', 'payments');
    }

    /**
     * @param  list<array{product_id?: ?int, description: string, quantity: float|int|string, unit_price: float|int|string, tax_rate?: float|int|string|null}>  $lines
     */
    private function replaceLines(Invoice $invoice, array $lines, float $defaultTaxRate): void
    {
        $invoice->lines()->delete();
        $total = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $rate = isset($line['tax_rate']) ? (float) $line['tax_rate'] : $defaultTaxRate;
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $total += $lineTotal;
            $tax += $lineTotal - $lineTotal / (1 + $rate / 100);

            $invoice->lines()->create([
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_rate' => $rate,
                'total' => $lineTotal,
            ]);
        }

        $invoice->update([
            'total' => round($total, 2),
            'tax_amount' => round($tax, 2),
            'subtotal' => round($total - round($tax, 2), 2),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'order_id' => $invoice->order_id,
            'customer_id' => $invoice->customer_id,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->tax_amount,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date->toDateString(),
        ];
    }
}

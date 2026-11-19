<?php

namespace App\Services\Erp;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase application: draft → confirmed → (partially) received, with stock
 * entries and weighted average cost on every receipt.
 */
class PurchaseService
{
    public function __construct(
        private DocumentNumberGenerator $numbers,
        private InventoryService $inventory,
        private ActivityLogger $activity,
        private EventOutbox $outbox,
    ) {}

    /**
     * @param  array{supplier_id: int, warehouse_id?: ?int, expected_at?: ?string, notes?: ?string, lines: list<array{product_id: int, quantity: int, unit_cost: float|string}>}  $data
     */
    public function create(Shop $shop, array $data, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($shop, $data, $user): PurchaseOrder {
            $order = $shop->purchaseOrders()->create([
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? $this->inventory->defaultWarehouse($shop)->id,
                'number' => $this->numbers->next($shop, 'purchase_order', 'PO-'),
                'status' => PurchaseOrderStatus::Draft,
                'expected_at' => $data['expected_at'] ?? null,
                'currency' => $shop->currency,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $this->replaceLines($order, $data['lines']);
            $this->activity->log($shop, 'purchase_order.created', "Bon de commande {$order->number} créé.", $order);

            return $order->load('supplier', 'warehouse', 'lines.product');
        });
    }

    /**
     * @param  array{supplier_id?: int, warehouse_id?: ?int, expected_at?: ?string, notes?: ?string, lines?: list<array{product_id: int, quantity: int, unit_cost: float|string}>}  $data
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if (! $order->status->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Seul un bon de commande en brouillon peut être modifié.']);
        }

        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            $attributes = collect($data)->except('lines');

            if ($attributes->has('warehouse_id') && $attributes->get('warehouse_id') === null) {
                $attributes->forget('warehouse_id');
            }

            $order->update($attributes->all());

            if (isset($data['lines'])) {
                $this->replaceLines($order, $data['lines']);
            }

            return $order->load('supplier', 'warehouse', 'lines.product');
        });
    }

    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Ce bon de commande est déjà confirmé ou clôturé.']);
        }

        if ($order->lines()->doesntExist()) {
            throw ValidationException::withMessages(['lines' => 'Ajoutez au moins un article avant de confirmer.']);
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => PurchaseOrderStatus::Confirmed, 'confirmed_at' => now()]);
            $this->activity->log($order->shop, 'purchase_order.confirmed', "Bon de commande {$order->number} confirmé auprès de {$order->supplier->name}.", $order);
            $this->outbox->record('purchase_order.confirmed', $this->eventPayload($order), $order->shop);
        });

        return $order->load('supplier', 'warehouse', 'lines.product');
    }

    /**
     * Receive goods. Without quantities, everything still expected is received.
     *
     * @param  array<int, int>|null  $quantities  remaining quantities to receive, keyed by line id
     */
    public function receive(PurchaseOrder $order, ?array $quantities = null): PurchaseOrder
    {
        if (! $order->status->canReceive()) {
            throw ValidationException::withMessages(['status' => 'Confirmez le bon de commande avant de réceptionner la marchandise.']);
        }

        DB::transaction(function () use ($order, $quantities): void {
            $received = 0;

            foreach ($order->lines()->with('product')->lockForUpdate()->get() as $line) {
                $quantity = $quantities === null ? $line->remainingQuantity() : (int) ($quantities[$line->id] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                if ($quantity > $line->remainingQuantity()) {
                    throw ValidationException::withMessages(['lines' => "Vous ne pouvez pas recevoir plus de {$line->remainingQuantity()} « {$line->product->name} »."]);
                }

                $this->updateAverageCost($line->product, $quantity, (float) $line->unit_cost);
                $this->inventory->move($line->product, $order->warehouse, $quantity, StockMovementType::Receipt, $order->number, $order);
                $line->increment('quantity_received', $quantity);
                $received += $quantity;
            }

            if ($received === 0) {
                throw ValidationException::withMessages(['lines' => 'Indiquez au moins une quantité reçue.']);
            }

            $complete = $order->lines()->whereColumn('quantity_received', '<', 'quantity')->doesntExist();

            $order->update([
                'status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $complete ? now() : null,
            ]);

            $this->activity->log($order->shop, 'purchase_order.received', $complete
                ? "Bon de commande {$order->number} entièrement réceptionné."
                : "Réception partielle du bon de commande {$order->number} ({$received} article(s)).", $order, ['quantity' => $received]);

            $this->outbox->record('purchase_order.received', [...$this->eventPayload($order), 'received_quantity' => $received], $order->shop);
        });

        return $order->load('supplier', 'warehouse', 'lines.product');
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Confirmed], true)) {
            throw ValidationException::withMessages(['status' => 'Un bon de commande déjà réceptionné ne peut pas être annulé.']);
        }

        $order->update(['status' => PurchaseOrderStatus::Cancelled]);
        $this->activity->log($order->shop, 'purchase_order.cancelled', "Bon de commande {$order->number} annulé.", $order);

        return $order->load('supplier', 'warehouse', 'lines.product');
    }

    /**
     * One-click replenishment: turn the inventory suggestions into draft purchase
     * orders, one per supplier. Products without a known supplier go to the
     * fallback supplier when one is given, and are skipped otherwise.
     *
     * @param  list<int>|null  $productIds  limit to these products
     * @return list<PurchaseOrder>
     */
    public function createFromSuggestions(Shop $shop, ?Supplier $fallbackSupplier = null, ?array $productIds = null, ?User $user = null): array
    {
        $bySupplier = [];

        foreach ($this->inventory->reorderSuggestions($shop) as $suggestion) {
            if ($productIds !== null && ! in_array($suggestion['product']['id'], $productIds, true)) {
                continue;
            }

            $supplierId = $suggestion['supplier']['id'] ?? $fallbackSupplier?->id;

            if ($supplierId === null) {
                continue;
            }

            $bySupplier[$supplierId][] = $suggestion;
        }

        $orders = [];

        foreach ($bySupplier as $supplierId => $suggestions) {
            $costs = Product::query()->whereKey(array_map(fn (array $item): int => $item['product']['id'], $suggestions))->pluck('cost_price', 'id');

            $orders[] = $this->create($shop, [
                'supplier_id' => $supplierId,
                'notes' => 'Généré depuis les suggestions de réapprovisionnement.',
                'lines' => array_map(fn (array $item): array => [
                    'product_id' => $item['product']['id'],
                    'quantity' => $item['suggested_quantity'],
                    'unit_cost' => (float) ($costs[$item['product']['id']] ?? 0),
                ], $suggestions),
            ], $user);
        }

        return $orders;
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost: float|string}>  $lines
     */
    private function replaceLines(PurchaseOrder $order, array $lines): void
    {
        $order->lines()->delete();
        $total = 0.0;

        foreach ($lines as $line) {
            $lineTotal = round((int) $line['quantity'] * (float) $line['unit_cost'], 2);
            $total += $lineTotal;

            $order->lines()->create([
                'product_id' => $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total' => $lineTotal,
            ]);
        }

        $order->update(['total' => round($total, 2)]);
    }

    /**
     * Weighted average cost, as in most ERPs: the new cost blends the value of
     * the stock on hand with the value of the goods received.
     */
    private function updateAverageCost(Product $product, int $quantity, float $unitCost): void
    {
        $onHand = max(0, (int) $product->stock_quantity);
        $currentCost = (float) ($product->cost_price ?? $unitCost);
        $newCost = $onHand + $quantity > 0 ? (($onHand * $currentCost) + ($quantity * $unitCost)) / ($onHand + $quantity) : $unitCost;

        $product->forceFill(['cost_price' => round($newCost, 2)])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(PurchaseOrder $order): array
    {
        return [
            'purchase_order_id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'supplier' => ['id' => $order->supplier_id, 'name' => $order->supplier?->name],
            'total' => (float) $order->total,
            'currency' => $order->currency,
        ];
    }
}

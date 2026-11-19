<?php

namespace App\Services\Erp;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrderLine;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock ledger of the Inventory application. `products.stock_quantity` stays the
 * total sold by the storefront; `product_stocks` splits it per warehouse and
 * every change is explained by a `stock_movements` line.
 */
class InventoryService
{
    public function __construct(private EventOutbox $outbox) {}

    /**
     * Prepare the shop when Inventory is installed: create the main warehouse and
     * align the per-warehouse quantities with the product totals.
     */
    public function bootstrap(Shop $shop): Warehouse
    {
        $warehouse = $this->defaultWarehouse($shop);

        $shop->products()->with('stocks')->each(function (Product $product) use ($warehouse): void {
            $difference = $product->stock_quantity - (int) $product->stocks->sum('quantity');

            if ($difference === 0) {
                return;
            }

            $type = $product->stocks->isEmpty() ? StockMovementType::Opening : StockMovementType::Adjustment;
            $this->applyToWarehouse($product, $warehouse, $difference, $type, note: 'Alignement sur le stock de la fiche produit.', updateProductTotal: false);
        });

        return $warehouse;
    }

    public function defaultWarehouse(Shop $shop): Warehouse
    {
        $warehouse = $shop->warehouses()->orderByDesc('is_default')->orderBy('id')->first();

        if ($warehouse === null) {
            return $shop->warehouses()->create(['name' => 'Entrepôt principal', 'code' => 'WH', 'is_default' => true]);
        }

        if (! $warehouse->is_default) {
            $warehouse->update(['is_default' => true]);
        }

        return $warehouse;
    }

    /**
     * Change the quantity of a product in a warehouse and record why.
     */
    public function move(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        StockMovementType $type,
        ?string $reference = null,
        ?Model $source = null,
        ?string $note = null,
        bool $allowNegative = false,
    ): StockMovement {
        return $this->applyToWarehouse($product, $warehouse, $quantity, $type, $reference, $source, $note, $allowNegative);
    }

    public function recordSale(Order $order, Product $product, int $quantity): StockMovement
    {
        return $this->applyToWarehouse(
            $product,
            $this->defaultWarehouse($order->shop),
            -$quantity,
            StockMovementType::Sale,
            $order->order_number,
            $order,
            allowNegative: true,
        );
    }

    public function recordSaleReturn(Order $order, Product $product, int $quantity): StockMovement
    {
        return $this->applyToWarehouse(
            $product,
            $this->defaultWarehouse($order->shop),
            $quantity,
            StockMovementType::SaleReturn,
            $order->order_number,
            $order,
            note: 'Commande annulée : articles remis en stock.',
        );
    }

    /**
     * Physical count: set the counted quantity and record the difference.
     */
    public function adjust(Product $product, Warehouse $warehouse, int $countedQuantity, ?string $note = null): ?StockMovement
    {
        $current = (int) ProductStock::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity');
        $difference = $countedQuantity - $current;

        if ($difference === 0) {
            return null;
        }

        return $this->applyToWarehouse($product, $warehouse, $difference, StockMovementType::Adjustment, note: $note ?? 'Inventaire physique.');
    }

    /**
     * Move goods between two warehouses; the product total is unchanged.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(Product $product, Warehouse $from, Warehouse $to, int $quantity, ?string $note = null): array
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Choisissez un entrepôt de destination différent.']);
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $note): array {
            $reference = "TR-{$from->code}-{$to->code}";

            return [
                $this->applyToWarehouse($product, $from, -$quantity, StockMovementType::TransferOut, $reference, note: $note),
                $this->applyToWarehouse($product, $to, $quantity, StockMovementType::TransferIn, $reference, note: $note),
            ];
        });
    }

    /**
     * Mirror in the ledger a stock change typed directly on the product form.
     */
    public function recordManualChange(Product $product, int $difference): ?StockMovement
    {
        if ($difference === 0) {
            return null;
        }

        return $this->applyToWarehouse(
            $product,
            $this->defaultWarehouse($product->shop),
            $difference,
            StockMovementType::Adjustment,
            note: 'Stock modifié depuis la fiche produit.',
            allowNegative: true,
            updateProductTotal: false,
        );
    }

    /**
     * Value of the stock at cost price.
     */
    public function stockValue(Shop $shop): float
    {
        return round((float) $shop->products()->sum(DB::raw('stock_quantity * COALESCE(cost_price, 0)')), 2);
    }

    /**
     * Replenishment proposals from the sales velocity, supplier lead time,
     * reorder point and quantities already on order.
     *
     * @return list<array{product: array{id: int, name: string, sku: ?string, image_url: ?string}, stock: int, incoming: int, reorder_point: int, sales_velocity: float, days_of_cover: ?float, stockout_date: ?string, suggested_quantity: int, estimated_cost: float, urgency: string, supplier: ?array{id: int, name: string, lead_time_days: int}}>
     */
    public function reorderSuggestions(Shop $shop): array
    {
        $window = (int) config('erp.inventory.sales_window_days');
        $coverage = (int) config('erp.inventory.coverage_days');
        $defaultLeadTime = (int) config('erp.inventory.default_lead_time_days');

        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', now()->subDays($window))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as sold')
            ->pluck('sold', 'product_id');

        $openLines = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', fn ($query) => $query->where('shop_id', $shop->id)->whereIn('status', [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived]))
            ->get(['product_id', 'quantity', 'quantity_received'])
            ->groupBy('product_id')
            ->map(fn ($lines) => $lines->sum(fn (PurchaseOrderLine $line) => $line->remainingQuantity()));

        $lastSuppliers = PurchaseOrderLine::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.shop_id', $shop->id)
            ->where('purchase_orders.status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->orderBy('purchase_orders.id')
            ->get(['purchase_order_lines.product_id', 'suppliers.id', 'suppliers.name', 'suppliers.lead_time_days'])
            ->keyBy('product_id');

        $suggestions = [];

        foreach ($shop->products()->orderBy('name')->get() as $product) {
            $velocity = ((int) ($sold[$product->id] ?? 0)) / max(1, $window);

            if ($velocity <= 0 && $product->reorder_point === null) {
                continue;
            }

            $supplier = $lastSuppliers->get($product->id);
            $leadTime = $supplier ? (int) $supplier->lead_time_days : $defaultLeadTime;
            $incoming = (int) ($openLines[$product->id] ?? 0);
            $stock = (int) $product->stock_quantity;
            $reorderPoint = $product->reorder_point ?? (int) ceil($velocity * $leadTime);

            if ($stock + $incoming > $reorderPoint) {
                continue;
            }

            $target = max((int) ceil($velocity * ($leadTime + $coverage)), $reorderPoint + 1);
            $suggested = max(1, $target - $stock - $incoming);
            $daysOfCover = $velocity > 0 ? round($stock / $velocity, 1) : null;

            $suggestions[] = [
                'product' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'image_url' => $product->image_url],
                'stock' => $stock,
                'incoming' => $incoming,
                'reorder_point' => $reorderPoint,
                'sales_velocity' => round($velocity, 2),
                'days_of_cover' => $daysOfCover,
                'stockout_date' => $velocity > 0 ? Carbon::today()->addDays((int) floor($stock / $velocity))->toDateString() : null,
                'suggested_quantity' => $suggested,
                'estimated_cost' => round($suggested * (float) ($product->cost_price ?? 0), 2),
                'urgency' => $stock <= 0 || ($daysOfCover !== null && $daysOfCover < $leadTime) ? 'critical' : 'warning',
                'supplier' => $supplier ? ['id' => (int) $supplier->id, 'name' => $supplier->name, 'lead_time_days' => $leadTime] : null,
            ];
        }

        usort($suggestions, fn (array $a, array $b): int => [$a['urgency'] === 'critical' ? 0 : 1, $a['days_of_cover'] ?? PHP_INT_MAX] <=> [$b['urgency'] === 'critical' ? 0 : 1, $b['days_of_cover'] ?? PHP_INT_MAX]);

        return $suggestions;
    }

    private function applyToWarehouse(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        StockMovementType $type,
        ?string $reference = null,
        ?Model $source = null,
        ?string $note = null,
        bool $allowNegative = false,
        bool $updateProductTotal = true,
    ): StockMovement {
        if ($warehouse->shop_id !== $product->shop_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Cet entrepôt n’appartient pas à la boutique du produit.']);
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $type, $reference, $source, $note, $allowNegative, $updateProductTotal): StockMovement {
            $stock = ProductStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first()
                ?? ProductStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 0]);

            $after = $stock->quantity + $quantity;

            if ($after < 0 && ! $allowNegative) {
                throw ValidationException::withMessages(['quantity' => "Stock insuffisant pour « {$product->name} » dans « {$warehouse->name} » ({$stock->quantity} disponible(s))."]);
            }

            $stock->update(['quantity' => $after]);

            if ($updateProductTotal) {
                $before = (int) Product::query()->whereKey($product->id)->lockForUpdate()->value('stock_quantity');
                $total = max(0, $before + $quantity);
                $product->forceFill(['stock_quantity' => $total, 'stock_status' => $total > 0 ? 'in_stock' : 'out_of_stock'])->save();

                $threshold = $product->reorder_point ?? AnalyticsService::LOW_STOCK_THRESHOLD;

                if ($before > $threshold && $total <= $threshold) {
                    $this->outbox->record('stock.low', ['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'stock' => $total, 'threshold' => $threshold], $product->shop);
                }
            }

            return StockMovement::create([
                'shop_id' => $product->shop_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'quantity' => $quantity,
                'warehouse_quantity_after' => $after,
                'reference' => $reference,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
                'user_id' => Auth::id(),
            ]);
        });
    }
}

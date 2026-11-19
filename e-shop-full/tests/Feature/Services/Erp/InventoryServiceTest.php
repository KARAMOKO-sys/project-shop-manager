<?php

namespace Tests\Feature\Services\Erp;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Erp\InventoryService;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function inventoryShop(int $stock = 10): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        Product::factory()->for($shop)->create(['stock_quantity' => $stock, 'price' => 5000]);
        app(ModuleRegistry::class)->install($shop, 'inventory');

        return $shop;
    }

    public function test_installation_creates_the_main_warehouse_with_opening_stock(): void
    {
        $shop = $this->inventoryShop(stock: 8);
        $product = $shop->products()->first();
        $warehouse = $shop->warehouses()->sole();

        $this->assertTrue($warehouse->is_default);
        $this->assertDatabaseHas('product_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 8]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'opening', 'quantity' => 8]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
    }

    public function test_storefront_checkout_records_a_sale_and_cancellation_records_a_return(): void
    {
        $shop = $this->inventoryShop(stock: 5);
        $product = $shop->products()->first();
        $warehouse = $shop->warehouses()->sole();

        $orderId = $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", [
            'customer' => ['first_name' => 'Fatou', 'last_name' => 'Sow', 'email' => 'fatou@example.com'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash_on_delivery',
            'delivery_method' => 'delivery',
            'delivery_address' => 'Rue 12, Cocody',
            'delivery_city' => 'Abidjan',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('product_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 3]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => -2, 'source_id' => $orderId]);
        $this->assertDatabaseHas('outbox_messages', ['event' => 'order.placed', 'shop_id' => $shop->id]);

        $this->patchJson("/api/v1/shops/{$shop->id}/orders/{$orderId}", ['status' => 'cancelled'])->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);
        $this->assertDatabaseHas('product_stocks', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 5]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale_return', 'quantity' => 2]);
    }

    public function test_stock_typed_on_the_product_form_is_recorded_as_an_adjustment(): void
    {
        $shop = $this->inventoryShop(stock: 10);
        $product = $shop->products()->first();

        $this->putJson("/api/v1/shops/{$shop->id}/products/{$product->id}", ['stock_quantity' => 14])->assertOk();

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => 4]);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 14]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 14]);
    }

    public function test_reorder_suggestion_uses_sales_velocity_and_lead_time(): void
    {
        $shop = $this->inventoryShop(stock: 3);
        $product = $shop->products()->first();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::Delivered, 'created_at' => now()->subDays(5)]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 30, 'unit_price' => 5000, 'total_price' => 150000]);

        $suggestions = app(InventoryService::class)->reorderSuggestions($shop);

        $this->assertCount(1, $suggestions);
        $this->assertSame(1.0, $suggestions[0]['sales_velocity']);
        $this->assertSame(7, $suggestions[0]['reorder_point']);
        $this->assertSame(18, $suggestions[0]['suggested_quantity']);
        $this->assertSame('critical', $suggestions[0]['urgency']);
        $this->assertSame(now()->addDays(3)->toDateString(), $suggestions[0]['stockout_date']);
    }

    public function test_quantities_already_on_order_reduce_the_suggestion(): void
    {
        $shop = $this->inventoryShop(stock: 2);
        app(ModuleRegistry::class)->install($shop, 'purchase');
        $product = $shop->products()->first();
        $product->update(['reorder_point' => 10]);
        $supplier = Supplier::factory()->for($shop)->create(['lead_time_days' => 3]);
        $purchase = PurchaseOrder::factory()->for($shop)->create(['supplier_id' => $supplier->id, 'warehouse_id' => $shop->warehouses()->first()->id, 'status' => PurchaseOrderStatus::Confirmed]);
        $purchase->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 100, 'total' => 500]);

        $suggestion = app(InventoryService::class)->reorderSuggestions($shop)[0];

        $this->assertSame(5, $suggestion['incoming']);
        $this->assertSame(4, $suggestion['suggested_quantity']);
        $this->assertSame($supplier->id, $suggestion['supplier']['id']);
        $this->assertSame(3, $suggestion['supplier']['lead_time_days']);
    }

    public function test_products_with_enough_stock_are_not_suggested(): void
    {
        $shop = $this->inventoryShop(stock: 50);
        $shop->products()->first()->update(['reorder_point' => 10]);

        $this->assertSame([], app(InventoryService::class)->reorderSuggestions($shop));
    }

    public function test_reinstalling_inventory_realigns_the_ledger_with_product_totals(): void
    {
        $shop = $this->inventoryShop(stock: 10);
        $product = $shop->products()->first();
        app(ModuleRegistry::class)->uninstall($shop, 'inventory');
        $product->update(['stock_quantity' => 6]);

        app(ModuleRegistry::class)->install($shop, 'inventory');

        $this->assertSame(1, $shop->warehouses()->count());
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 6]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => -4]);
    }
}

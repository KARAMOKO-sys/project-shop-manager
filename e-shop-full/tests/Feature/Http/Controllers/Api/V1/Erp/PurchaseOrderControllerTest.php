<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function purchaseShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'purchase');

        return $shop;
    }

    /**
     * @return array{0: Shop, 1: Supplier, 2: Product}
     */
    private function shopWithCatalog(int $stock = 10, float $cost = 1000): array
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => $stock, 'cost_price' => $cost]);
        app(ModuleRegistry::class)->install($shop, 'purchase');

        return [$shop, Supplier::factory()->for($shop)->create(), $product];
    }

    private function createOrder(Shop $shop, Supplier $supplier, Product $product, int $quantity = 10, float $unitCost = 2000): int
    {
        return $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders", [
            'supplier_id' => $supplier->id,
            'lines' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost]],
        ])->assertCreated()->json('data.id');
    }

    public function test_installing_purchase_also_installs_inventory(): void
    {
        $shop = $this->purchaseShop();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/warehouses")->assertOk();
    }

    public function test_store_creates_a_numbered_draft_in_the_default_warehouse(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders", [
            'supplier_id' => $supplier->id,
            'expected_at' => now()->addWeek()->toDateString(),
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1500],
                ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 250],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.number', 'PO-00001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.status_label', 'Brouillon')
            ->assertJsonPath('data.total', 15500)
            ->assertJsonPath('data.warehouse.code', 'WH')
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.lines.0.remaining_quantity', 10)
            ->assertJsonPath('data.is_late', false);
    }

    public function test_store_rejects_supplier_product_and_warehouse_of_another_shop(): void
    {
        [$shop] = $this->shopWithCatalog();
        $foreignOrder = PurchaseOrder::factory()->create();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders", [
            'supplier_id' => $foreignOrder->supplier_id,
            'warehouse_id' => $foreignOrder->warehouse_id,
            'lines' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_cost' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'warehouse_id', 'lines.0.product_id']);
    }

    public function test_update_is_refused_once_confirmed(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();
        $id = $this->createOrder($shop, $supplier, $product);

        $this->putJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}", ['notes' => 'Urgent'])->assertOk()->assertJsonPath('data.notes', 'Urgent');
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->putJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}", ['notes' => 'Trop tard'])->assertUnprocessable();
    }

    public function test_receiving_a_draft_is_refused(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();
        $id = $this->createOrder($shop, $supplier, $product);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_full_receipt_adds_stock_and_updates_weighted_average_cost(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog(stock: 10, cost: 1000);
        $id = $this->createOrder($shop, $supplier, $product, quantity: 10, unitCost: 2000);
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/confirm")->assertOk();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.lines.0.quantity_received', 10)
            ->assertJsonPath('data.lines.0.remaining_quantity', 0);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 20, 'cost_price' => 1500]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'receipt', 'quantity' => 10, 'source_id' => $id]);
    }

    public function test_partial_receipt_then_the_rest(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog(stock: 0);
        $id = $this->createOrder($shop, $supplier, $product, quantity: 10);
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/confirm")->assertOk();
        $lineId = PurchaseOrder::find($id)->lines()->value('id');

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive", ['lines' => [['id' => $lineId, 'quantity' => 4]]])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_received')
            ->assertJsonPath('data.lines.0.remaining_quantity', 6);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive", ['lines' => [['id' => $lineId, 'quantity' => 7]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive")->assertOk()->assertJsonPath('data.status', 'received');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    public function test_receive_rejects_a_line_of_another_purchase_order(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();
        $id = $this->createOrder($shop, $supplier, $product);
        $otherId = $this->createOrder($shop, $supplier, $product);
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/confirm")->assertOk();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive", ['lines' => [['id' => PurchaseOrder::find($otherId)->lines()->value('id'), 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.id');
    }

    public function test_cancel_a_confirmed_order(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();
        $id = $this->createOrder($shop, $supplier, $product);
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/confirm")->assertOk();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$id}/receive")->assertUnprocessable();
    }

    public function test_index_filters_by_status(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog();
        $this->createOrder($shop, $supplier, $product);
        $confirmed = $this->createOrder($shop, $supplier, $product);
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$confirmed}/confirm")->assertOk();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/purchase-orders?status=confirmed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $confirmed)
            ->assertJsonPath('data.0.lines_count', 1);
    }

    public function test_from_suggestions_creates_one_draft_per_supplier(): void
    {
        [$shop, $supplier, $product] = $this->shopWithCatalog(stock: 2, cost: 800);
        $product->update(['reorder_point' => 5]);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/from-suggestions")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/from-suggestions", ['supplier_id' => $supplier->id])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.supplier.id', $supplier->id)
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.lines.0.quantity', 4)
            ->assertJsonPath('data.0.lines.0.unit_cost', 800);
    }

    public function test_purchase_order_of_another_shop_returns_404(): void
    {
        $shop = $this->purchaseShop();
        $foreign = PurchaseOrder::factory()->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/shops/{$shop->id}/erp/purchase-orders/{$foreign->id}/confirm")->assertNotFound();
    }
}

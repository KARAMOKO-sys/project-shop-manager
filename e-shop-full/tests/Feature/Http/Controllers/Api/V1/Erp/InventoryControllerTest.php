<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function inventoryShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        Product::factory()->for($shop)->create(['name' => 'Pagne wax', 'stock_quantity' => 10, 'cost_price' => 2000]);
        app(ModuleRegistry::class)->install($shop, 'inventory');

        return $shop;
    }

    public function test_stock_lists_products_with_valuation_and_split_per_warehouse(): void
    {
        $shop = $this->inventoryShop();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/inventory/stock")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Pagne wax')
            ->assertJsonPath('data.0.stock_quantity', 10)
            ->assertJsonPath('data.0.stock_value', 20000)
            ->assertJsonPath('data.0.warehouses.0.code', 'WH')
            ->assertJsonPath('data.0.warehouses.0.quantity', 10)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_adjustment_sets_the_counted_quantity_and_records_the_difference(): void
    {
        $shop = $this->inventoryShop();
        $product = $shop->products()->first();
        $warehouse = $shop->warehouses()->first();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/inventory/adjustments", ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'counted_quantity' => 7])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', -3)
            ->assertJsonPath('data.warehouse_quantity_after', 7);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7]);
    }

    public function test_adjustment_without_difference_returns_null(): void
    {
        $shop = $this->inventoryShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/inventory/adjustments", [
            'product_id' => $shop->products()->first()->id,
            'warehouse_id' => $shop->warehouses()->first()->id,
            'counted_quantity' => 10,
        ])->assertOk()->assertJsonPath('data', null);
    }

    public function test_adjustment_rejects_warehouse_and_product_of_another_shop(): void
    {
        $shop = $this->inventoryShop();
        $foreignWarehouse = Warehouse::factory()->create();
        $foreignProduct = Product::factory()->create();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/inventory/adjustments", ['product_id' => $foreignProduct->id, 'warehouse_id' => $foreignWarehouse->id, 'counted_quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'warehouse_id']);
    }

    public function test_transfer_moves_goods_without_changing_the_total(): void
    {
        $shop = $this->inventoryShop();
        $product = $shop->products()->first();
        $main = $shop->warehouses()->first();
        $annex = Warehouse::factory()->for($shop)->create(['code' => 'ANX']);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/inventory/transfers", ['product_id' => $product->id, 'from_warehouse_id' => $main->id, 'to_warehouse_id' => $annex->id, 'quantity' => 4])
            ->assertCreated()
            ->assertJsonPath('data.0.type', 'transfer_out')
            ->assertJsonPath('data.1.type', 'transfer_in');

        $this->assertDatabaseHas('product_stocks', ['warehouse_id' => $main->id, 'product_id' => $product->id, 'quantity' => 6]);
        $this->assertDatabaseHas('product_stocks', ['warehouse_id' => $annex->id, 'product_id' => $product->id, 'quantity' => 4]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    public function test_transfer_of_more_than_available_is_rejected(): void
    {
        $shop = $this->inventoryShop();
        $annex = Warehouse::factory()->for($shop)->create();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/inventory/transfers", [
            'product_id' => $shop->products()->first()->id,
            'from_warehouse_id' => $shop->warehouses()->where('is_default', true)->first()->id,
            'to_warehouse_id' => $annex->id,
            'quantity' => 50,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_movements_can_be_filtered_by_product(): void
    {
        $shop = $this->inventoryShop();
        $product = $shop->products()->first();
        Product::factory()->for($shop)->create(['stock_quantity' => 0]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/inventory/movements?product_id={$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'opening')
            ->assertJsonPath('data.0.type_label', 'Stock d’ouverture')
            ->assertJsonPath('data.0.product.id', $product->id)
            ->assertJsonPath('data.0.warehouse.code', 'WH');
    }

    public function test_update_product_sets_reorder_point_and_cost(): void
    {
        $shop = $this->inventoryShop();
        $product = $shop->products()->first();

        $this->patchJson("/api/v1/shops/{$shop->id}/erp/inventory/products/{$product->id}", ['reorder_point' => 12, 'cost_price' => 2500])
            ->assertOk()
            ->assertJsonPath('data.reorder_point', 12)
            ->assertJsonPath('data.cost_price', 2500);
    }

    public function test_update_product_of_another_shop_returns_404(): void
    {
        $shop = $this->inventoryShop();
        $foreign = Product::factory()->create();

        $this->patchJson("/api/v1/shops/{$shop->id}/erp/inventory/products/{$foreign->id}", ['reorder_point' => 1])->assertNotFound();
    }

    public function test_suggestions_list_products_below_their_reorder_point(): void
    {
        $shop = $this->inventoryShop();
        $shop->products()->first()->update(['reorder_point' => 15]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/inventory/reorder-suggestions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.name', 'Pagne wax')
            ->assertJsonPath('data.0.reorder_point', 15)
            ->assertJsonPath('data.0.suggested_quantity', 6);
    }

    public function test_another_merchant_cannot_read_the_inventory(): void
    {
        $shop = $this->inventoryShop();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/shops/{$shop->id}/erp/inventory/stock")->assertForbidden();
    }
}

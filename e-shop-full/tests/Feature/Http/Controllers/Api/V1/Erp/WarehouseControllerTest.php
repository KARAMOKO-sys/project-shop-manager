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

class WarehouseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function inventoryShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'inventory');

        return $shop;
    }

    public function test_index_lists_the_default_warehouse_created_at_installation(): void
    {
        $shop = $this->inventoryShop();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/warehouses")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'WH')
            ->assertJsonPath('data.0.is_default', true);
    }

    public function test_store_uppercases_code_and_can_become_the_default(): void
    {
        $shop = $this->inventoryShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/warehouses", ['name' => 'Dépôt Yopougon', 'code' => 'yop', 'is_default' => true])
            ->assertCreated()
            ->assertJsonPath('data.code', 'YOP')
            ->assertJsonPath('data.is_default', true);

        $this->assertSame(1, $shop->warehouses()->where('is_default', true)->count());
    }

    public function test_store_rejects_a_code_already_used_in_the_shop(): void
    {
        $shop = $this->inventoryShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/warehouses", ['name' => 'Autre', 'code' => 'wh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_destroy_refuses_the_default_warehouse_and_a_warehouse_holding_stock(): void
    {
        $shop = $this->inventoryShop();
        $default = $shop->warehouses()->first();
        $other = Warehouse::factory()->for($shop)->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 0]);
        $other->stocks()->create(['product_id' => $product->id, 'quantity' => 3]);

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/warehouses/{$default->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/warehouses/{$other->id}")->assertUnprocessable();
    }

    public function test_destroy_deletes_an_empty_warehouse(): void
    {
        $shop = $this->inventoryShop();
        $empty = Warehouse::factory()->for($shop)->create();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/warehouses/{$empty->id}")->assertNoContent();
        $this->assertModelMissing($empty);
    }

    public function test_routes_are_blocked_until_inventory_is_installed(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/warehouses")->assertForbidden();
    }

    public function test_warehouse_of_another_shop_returns_404(): void
    {
        $shop = $this->inventoryShop();
        $foreign = Warehouse::factory()->create();

        $this->putJson("/api/v1/shops/{$shop->id}/erp/warehouses/{$foreign->id}", ['name' => 'Piraté'])->assertNotFound();
    }
}

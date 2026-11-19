<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function purchaseShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'purchase');

        return $shop;
    }

    public function test_store_creates_supplier_with_default_terms(): void
    {
        $shop = $this->purchaseShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/suppliers", ['name' => 'Tissus Abidjan', 'phone' => '+2250700000000'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tissus Abidjan')
            ->assertJsonPath('data.payment_terms_days', 0)
            ->assertJsonPath('data.lead_time_days', 7);
    }

    public function test_index_lists_suppliers_with_their_purchase_order_count(): void
    {
        $shop = $this->purchaseShop();
        $supplier = Supplier::factory()->for($shop)->create();
        PurchaseOrder::factory()->for($shop)->create(['supplier_id' => $supplier->id]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/suppliers")
            ->assertOk()
            ->assertJsonPath('data.0.purchase_orders_count', 1);
    }

    public function test_update_changes_lead_time(): void
    {
        $shop = $this->purchaseShop();
        $supplier = Supplier::factory()->for($shop)->create();

        $this->putJson("/api/v1/shops/{$shop->id}/erp/suppliers/{$supplier->id}", ['lead_time_days' => 3])
            ->assertOk()
            ->assertJsonPath('data.lead_time_days', 3);
    }

    public function test_destroy_refuses_a_supplier_with_purchase_orders(): void
    {
        $shop = $this->purchaseShop();
        $supplier = Supplier::factory()->for($shop)->create();
        PurchaseOrder::factory()->for($shop)->create(['supplier_id' => $supplier->id]);

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/suppliers/{$supplier->id}")->assertUnprocessable();
        $this->assertModelExists($supplier);
    }

    public function test_destroy_deletes_an_unused_supplier(): void
    {
        $shop = $this->purchaseShop();
        $supplier = Supplier::factory()->for($shop)->create();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/suppliers/{$supplier->id}")->assertNoContent();
        $this->assertModelMissing($supplier);
    }

    public function test_supplier_of_another_shop_returns_404(): void
    {
        $shop = $this->purchaseShop();
        $foreign = Supplier::factory()->create();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/suppliers/{$foreign->id}")->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_routes_are_blocked_until_purchase_is_installed(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'inventory');

        $this->getJson("/api/v1/shops/{$shop->id}/erp/suppliers")->assertForbidden();
    }
}

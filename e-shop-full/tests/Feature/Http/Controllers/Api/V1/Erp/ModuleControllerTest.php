<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function merchantShop(): Shop
    {
        return Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
    }

    public function test_index_lists_catalog_with_only_core_installed(): void
    {
        $shop = $this->merchantShop();

        $response = $this->getJson("/api/v1/shops/{$shop->id}/erp/modules")->assertOk();

        $installed = collect($response->json('data'))->where('installed', true)->pluck('key')->all();
        $this->assertSame(['sales'], $installed);
        $this->assertSame(['inventory'], collect($response->json('data'))->firstWhere('key', 'purchase')['depends']);
    }

    public function test_installing_purchase_also_installs_inventory_and_bootstraps_stock(): void
    {
        $shop = $this->merchantShop();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 12]);

        $response = $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/purchase")
            ->assertCreated()
            ->assertJsonPath('installed', ['inventory', 'purchase']);

        $this->assertSame(['purchase'], collect($response->json('data'))->firstWhere('key', 'inventory')['required_by']);
        $this->assertDatabaseHas('warehouses', ['shop_id' => $shop->id, 'code' => 'WH', 'is_default' => true]);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 12]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'opening', 'quantity' => 12]);
        $this->assertDatabaseHas('outbox_messages', ['shop_id' => $shop->id, 'event' => 'module.installed']);

        $this->getJson("/api/v1/shops/{$shop->id}")->assertJsonPath('data.modules', ['sales', 'inventory', 'purchase']);
    }

    public function test_installing_an_installed_module_is_a_no_op(): void
    {
        $shop = $this->merchantShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/crm")->assertCreated();
        $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/crm")->assertOk()->assertJsonPath('installed', []);
        $this->assertSame(1, $shop->modules()->count());
    }

    public function test_dependency_cannot_be_uninstalled_while_required(): void
    {
        $shop = $this->merchantShop();
        $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/purchase")->assertCreated();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/modules/inventory")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/modules/purchase")->assertOk();
        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/modules/inventory")->assertOk();
        $this->assertSame(0, $shop->modules()->count());
    }

    public function test_core_module_cannot_be_uninstalled(): void
    {
        $shop = $this->merchantShop();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/modules/sales")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');
    }

    public function test_unknown_module_is_rejected(): void
    {
        $shop = $this->merchantShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/payroll")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');
    }

    public function test_uninstalled_module_routes_are_blocked(): void
    {
        $shop = $this->merchantShop();
        $this->postJson("/api/v1/shops/{$shop->id}/erp/modules/crm")->assertCreated();
        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads")->assertOk();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/modules/crm")->assertOk();
        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads")->assertForbidden();
    }

    public function test_modules_of_another_merchant_shop_are_forbidden(): void
    {
        $this->merchantShop();
        $foreign = Shop::factory()->create();

        $this->getJson("/api/v1/shops/{$foreign->id}/erp/modules")->assertForbidden();
        $this->postJson("/api/v1/shops/{$foreign->id}/erp/modules/crm")->assertForbidden();
        $this->assertSame(0, $foreign->modules()->count());
    }

    public function test_guest_is_unauthorized(): void
    {
        $shop = Shop::factory()->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/modules")->assertUnauthorized();
    }
}

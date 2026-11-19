<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Enums\OrderStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ErpDashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function merchantShop(): Shop
    {
        return Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
    }

    public function test_dashboard_only_shows_sales_without_extra_modules(): void
    {
        $shop = $this->merchantShop();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/dashboard")
            ->assertOk()
            ->assertJsonPath('data.modules', ['sales'])
            ->assertJsonPath('data.period_days', 30)
            ->assertJsonMissingPath('data.inventory')
            ->assertJsonMissingPath('data.expenses');
    }

    public function test_dashboard_computes_margin_and_net_profit(): void
    {
        $shop = $this->merchantShop();
        app(ModuleRegistry::class)->install($shop, 'expenses');
        app(ModuleRegistry::class)->install($shop, 'invoicing');

        $product = Product::factory()->for($shop)->create(['price' => 10000, 'cost_price' => 6000]);
        $order = Order::factory()->for($shop)->create(['total' => 20000, 'subtotal' => 20000]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 10000, 'total_price' => 20000]);
        Order::factory()->for($shop)->create(['total' => 50000, 'status' => OrderStatus::Cancelled]);
        Expense::factory()->for($shop)->create(['amount' => 3000, 'category' => 'transport']);
        Invoice::factory()->for($shop)->posted()->create(['total' => 8000, 'amount_paid' => 3000, 'due_date' => today()->subDays(3)]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/dashboard?days=7")
            ->assertOk()
            ->assertJsonPath('data.period_days', 7)
            ->assertJsonPath('data.sales.revenue', 20000)
            ->assertJsonPath('data.sales.orders_count', 1)
            ->assertJsonPath('data.sales.cost_of_goods', 12000)
            ->assertJsonPath('data.sales.gross_margin', 8000)
            ->assertJsonPath('data.expenses.total', 3000)
            ->assertJsonPath('data.expenses.net_profit', 5000)
            ->assertJsonPath('data.expenses.by_category.transport', 3000)
            ->assertJsonPath('data.invoicing.receivables', 5000)
            ->assertJsonPath('data.invoicing.overdue_count', 1);
    }

    public function test_dashboard_of_another_merchant_is_forbidden(): void
    {
        $this->merchantShop();

        $this->getJson('/api/v1/shops/'.Shop::factory()->create()->id.'/erp/dashboard')->assertForbidden();
    }
}

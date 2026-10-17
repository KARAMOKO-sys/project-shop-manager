<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_creates_shop_with_template_default_categories_and_returns_201(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/shops', ['name' => 'Saveurs de Dakar', 'template' => 'food']);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'saveurs-de-dakar')
            ->assertJsonPath('data.template', 'food')
            ->assertJsonPath('data.theme.primary', config('shopai.templates.food.theme.primary'))
            ->assertJsonCount(4, 'data.categories');
        $this->assertDatabaseHas('shops', ['user_id' => $user->id, 'name' => 'Saveurs de Dakar']);
    }

    public function test_store_uses_custom_categories_and_suffixes_taken_slug(): void
    {
        Shop::factory()->create(['slug' => 'ma-boutique']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/shops', [
            'name' => 'Ma Boutique',
            'template' => 'minimal',
            'categories' => ['Sacs', 'Chaussures', 'sacs'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ma-boutique-2')
            ->assertJsonPath('data.categories.*.name', ['Sacs', 'Chaussures']);
    }

    public function test_store_rejects_unknown_template_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/shops', ['name' => 'Boutique', 'template' => 'cyberpunk'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('template');
    }

    public function test_store_returns_401_without_token(): void
    {
        $this->postJson('/api/v1/shops', ['name' => 'Boutique', 'template' => 'minimal'])->assertUnauthorized();
    }

    public function test_index_lists_only_the_merchants_own_shops(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $ownShop = Shop::factory()->for($user, 'owner')->create();
        Shop::factory()->create();

        $this->getJson('/api/v1/shops')->assertOk()->assertJsonPath('data.*.id', [$ownShop->id]);
    }

    public function test_show_forbids_another_merchants_shop_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->create();

        $this->getJson("/api/v1/shops/{$shop->id}")->assertForbidden();
    }

    public function test_update_merges_theme_overrides_with_template_theme(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create(['template' => 'tech']);

        $this->putJson("/api/v1/shops/{$shop->id}", ['theme' => ['primary' => '#112233']])
            ->assertOk()
            ->assertJsonPath('data.theme.primary', '#112233')
            ->assertJsonPath('data.theme.secondary', config('shopai.templates.tech.theme.secondary'));

        $this->putJson("/api/v1/shops/{$shop->id}", ['theme' => ['font' => 'Lora', 'radius' => '14px']])
            ->assertOk()
            ->assertJsonPath('data.theme.primary', '#112233')
            ->assertJsonPath('data.theme.font', 'Lora')
            ->assertJsonPath('data.theme.radius', '14px');
    }

    public function test_update_switches_the_shop_format(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create(['template' => 'restaurant']);

        $this->putJson("/api/v1/shops/{$shop->id}", ['theme' => ['format' => 'catalog']])
            ->assertOk()
            ->assertJsonPath('data.theme.format', 'catalog')
            ->assertJsonPath('data.theme.primary', config('shopai.templates.restaurant.theme.primary'));

        $this->putJson("/api/v1/shops/{$shop->id}", ['theme' => ['format' => 'carousel']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme.format');
    }

    public function test_update_rejects_invalid_theme_color_with_422(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();

        $this->putJson("/api/v1/shops/{$shop->id}", ['theme' => ['primary' => 'red; background:url(x)']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme.primary');
    }

    public function test_update_encrypts_flutterwave_credentials_and_never_returns_them(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();
        $secretKey = 'FLWSECK_TEST-merchant-secret';
        $webhookSecret = 'merchant-webhook-secret';

        $response = $this->putJson("/api/v1/shops/{$shop->id}", [
            'flutterwave_secret_key' => $secretKey,
            'flutterwave_webhook_secret_hash' => $webhookSecret,
            'flutterwave_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.flutterwave_configured', true)
            ->assertJsonPath('data.flutterwave_enabled', true)
            ->assertJsonPath('data.payment_methods', ['cash_on_delivery', 'flutterwave']);

        $this->assertNotSame($secretKey, DB::table('shops')->where('id', $shop->id)->value('flutterwave_secret_key'));
        $this->assertNotSame($webhookSecret, DB::table('shops')->where('id', $shop->id)->value('flutterwave_webhook_secret_hash'));
        $this->assertSame($secretKey, $shop->fresh()->flutterwave_secret_key);
        $this->assertSame($webhookSecret, $shop->fresh()->flutterwave_webhook_secret_hash);
        $this->assertStringNotContainsString($secretKey, $response->getContent());
        $this->assertStringNotContainsString($webhookSecret, $response->getContent());
    }

    public function test_update_rejects_enabling_flutterwave_without_both_credentials_with_422(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();

        $this->putJson("/api/v1/shops/{$shop->id}", ['flutterwave_enabled' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('flutterwave_secret_key');
    }

    public function test_update_forbids_another_merchant_from_changing_flutterwave_credentials(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->create();

        $this->putJson("/api/v1/shops/{$shop->id}", [
            'flutterwave_secret_key' => 'FLWSECK_TEST-merchant-secret',
            'flutterwave_webhook_secret_hash' => 'merchant-webhook-secret',
            'flutterwave_enabled' => true,
        ])->assertForbidden();

        $this->assertNull($shop->fresh()->flutterwave_secret_key);
    }

    public function test_changing_template_resets_theme_overrides(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create(['template' => 'tech', 'theme' => ['primary' => '#112233']]);

        $this->putJson("/api/v1/shops/{$shop->id}", ['template' => 'beauty'])
            ->assertOk()
            ->assertJsonPath('data.theme.primary', config('shopai.templates.beauty.theme.primary'));
    }

    public function test_analytics_excludes_cancelled_orders_from_revenue(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();
        Order::factory()->for($shop)->create(['total' => 10_000]);
        Order::factory()->for($shop)->create(['total' => 30_000]);
        Order::factory()->for($shop)->create(['total' => 99_000, 'status' => OrderStatus::Cancelled]);

        $this->getJson("/api/v1/shops/{$shop->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.revenue', 40000)
            ->assertJsonPath('data.orders', 2)
            ->assertJsonPath('data.average_order_value', 20000)
            ->assertJsonPath('data.orders_by_status.cancelled', 1);
    }

    public function test_analytics_reports_low_stock_products_and_daily_revenue(): void
    {
        $this->freezeTime();
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();
        $lowStock = Product::factory()->for($shop)->create(['stock_quantity' => 2]);
        Product::factory()->for($shop)->create(['stock_quantity' => 50]);
        Product::factory()->for($shop)->draft()->create(['stock_quantity' => 0]);
        Order::factory()->for($shop)->create(['total' => 5000]);
        Order::factory()->for($shop)->create(['total' => 7000, 'created_at' => now()->subDays(3)]);
        Order::factory()->for($shop)->create(['total' => 9000, 'created_at' => now()->subDays(40)]);

        $response = $this->getJson("/api/v1/shops/{$shop->id}/analytics")->assertOk();

        $response->assertJsonPath('data.low_stock_products', [['id' => $lowStock->id, 'name' => $lowStock->name, 'stock_quantity' => 2]])
            ->assertJsonCount(30, 'data.revenue_by_day')
            ->assertJsonPath('data.revenue_by_day.29', ['date' => now()->toDateString(), 'revenue' => 5000, 'orders' => 1])
            ->assertJsonPath('data.revenue_by_day.26', ['date' => now()->subDays(3)->toDateString(), 'revenue' => 7000, 'orders' => 1]);
        $this->assertSame(12000.0, (float) collect($response->json('data.revenue_by_day'))->sum('revenue'));
    }
}

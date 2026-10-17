<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\AI\ModerationResult;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $values
     */
    private function configure(array $values): void
    {
        app(PlatformSettings::class)->update($values);
    }

    public function test_public_settings_expose_branding_and_features_but_not_internal_limits(): void
    {
        $this->getJson('/api/v1/platform')
            ->assertOk()
            ->assertJsonPath('data.platform.name', 'ShopAI')
            ->assertJsonPath('data.features.registration', true)
            ->assertJsonPath('data.payments.cash_on_delivery', true)
            ->assertJsonMissingPath('data.limits')
            ->assertJsonMissingPath('data.ai');
    }

    public function test_admin_update_is_persisted_and_reflected_in_public_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/admin/settings', [
            'platform' => ['name' => 'Marché Ivoire', 'announcement' => 'Livraison offerte ce week-end !'],
            'features' => ['shop_chat' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.values', fn (array $values): bool => $values['platform.name'] === 'Marché Ivoire' && $values['features.shop_chat'] === false);

        $this->getJson('/api/v1/platform')
            ->assertJsonPath('data.platform.name', 'Marché Ivoire')
            ->assertJsonPath('data.platform.announcement', 'Livraison offerte ce week-end !')
            ->assertJsonPath('data.features.shop_chat', false);
        $this->assertDatabaseHas('platform_settings', ['key' => 'platform.name']);
    }

    public function test_admin_settings_are_forbidden_to_merchants_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings', ['features' => ['registration' => false]])->assertForbidden();
    }

    public function test_update_rejects_reject_threshold_not_above_review_threshold_with_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/admin/settings', ['moderation' => ['review_threshold' => 60, 'reject_threshold' => 50]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('moderation.reject_threshold');
    }

    public function test_registration_is_refused_when_disabled(): void
    {
        $this->configure(['features' => ['registration' => false]]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_shop_chat_is_refused_when_disabled(): void
    {
        $this->configure(['features' => ['shop_chat' => false]]);
        $shop = Shop::factory()->create();

        $this->postJson("/api/v1/storefront/{$shop->slug}/chat", ['message' => 'Bonjour'])->assertForbidden();
    }

    public function test_shop_creation_is_refused_beyond_the_per_merchant_limit(): void
    {
        $this->configure(['limits' => ['max_shops_per_user' => 1]]);
        $merchant = User::factory()->create();
        Shop::factory()->for($merchant, 'owner')->create();
        Sanctum::actingAs($merchant);

        $this->postJson('/api/v1/shops', ['name' => 'Deuxième boutique', 'template' => 'minimal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_product_creation_is_refused_beyond_the_per_shop_limit(): void
    {
        $this->configure(['limits' => ['max_products_per_shop' => 1]]);
        $product = Product::factory()->create();
        Sanctum::actingAs($product->shop->owner);

        $this->postJson("/api/v1/shops/{$product->shop_id}/products", ['name' => 'Un produit de trop', 'price' => 1000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_checkout_refuses_cash_on_delivery_when_disabled_platform_wide(): void
    {
        $this->configure(['payments' => ['cash_on_delivery' => false]]);
        $product = Product::factory()->create();

        $this->postJson("/api/v1/storefront/{$product->shop->slug}/checkout", [
            'customer' => ['first_name' => 'Fatou', 'last_name' => 'Sow', 'email' => 'fatou@example.com'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash_on_delivery',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_moderation_thresholds_follow_the_settings(): void
    {
        $result = new ModerationResult(riskScore: 40, reasons: [], engine: 'local');
        $this->assertSame(ModerationStatus::PendingReview, $result->status());

        $this->configure(['moderation' => ['review_threshold' => 50, 'reject_threshold' => 80]]);

        $this->assertSame(ModerationStatus::Approved, $result->status());
    }
}

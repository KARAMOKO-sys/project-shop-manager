<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Coupon;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function merchantShop(): Shop
    {
        return Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
    }

    public function test_store_creates_uppercased_coupon_with_defaults_and_returns_201(): void
    {
        $shop = $this->merchantShop();

        $this->postJson("/api/v1/shops/{$shop->id}/coupons", ['code' => 'tabaski25', 'type' => 'percentage', 'value' => 25])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TABASKI25')
            ->assertJsonPath('data.usage_limit', 0)
            ->assertJsonPath('data.times_used', 0)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_redeemable', true);
    }

    public function test_store_rejects_code_already_used_in_the_same_shop_with_422(): void
    {
        $shop = $this->merchantShop();
        Coupon::create(['shop_id' => $shop->id, 'code' => 'PROMO', 'type' => 'fixed', 'value' => 500]);

        $this->postJson("/api/v1/shops/{$shop->id}/coupons", ['code' => 'promo', 'type' => 'fixed', 'value' => 1000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_store_allows_same_code_as_another_shop(): void
    {
        Coupon::create(['shop_id' => Shop::factory()->create()->id, 'code' => 'PROMO', 'type' => 'fixed', 'value' => 500]);
        $shop = $this->merchantShop();

        $this->postJson("/api/v1/shops/{$shop->id}/coupons", ['code' => 'PROMO', 'type' => 'fixed', 'value' => 1000])->assertCreated();
    }

    public function test_percentage_above_100_is_rejected_on_update_too(): void
    {
        $shop = $this->merchantShop();
        $coupon = Coupon::create(['shop_id' => $shop->id, 'code' => 'PROMO', 'type' => 'percentage', 'value' => 10]);

        $this->putJson("/api/v1/shops/{$shop->id}/coupons/{$coupon->id}", ['value' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');
    }

    public function test_coupon_of_another_shop_returns_404(): void
    {
        $shop = $this->merchantShop();
        $foreignCoupon = Coupon::create(['shop_id' => Shop::factory()->create()->id, 'code' => 'X', 'type' => 'fixed', 'value' => 1]);

        $this->deleteJson("/api/v1/shops/{$shop->id}/coupons/{$foreignCoupon->id}")->assertNotFound();
        $this->assertModelExists($foreignCoupon);
    }

    public function test_check_returns_discount_for_a_valid_code(): void
    {
        $shop = Shop::factory()->create();
        Coupon::create(['shop_id' => $shop->id, 'code' => 'PROMO10', 'type' => 'percentage', 'value' => 10]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/coupons/check", ['code' => 'promo10', 'subtotal' => 25000])
            ->assertOk()
            ->assertJsonPath('data.discount_amount', 2500);
    }

    public function test_check_rejects_code_below_minimum_amount_with_422(): void
    {
        $shop = Shop::factory()->create();
        Coupon::create(['shop_id' => $shop->id, 'code' => 'BIG', 'type' => 'fixed', 'value' => 1000, 'min_order_amount' => 20000]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/coupons/check", ['code' => 'BIG', 'subtotal' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Ce code promo est invalide ou expiré.']);
    }

    public function test_date_only_end_keeps_coupon_valid_until_the_end_of_that_day(): void
    {
        $shop = $this->merchantShop();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(18, 0));

        $this->postJson("/api/v1/shops/{$shop->id}/coupons", ['code' => 'JOUR', 'type' => 'fixed', 'value' => 500, 'ends_at' => '2026-10-10'])
            ->assertCreated()
            ->assertJsonPath('data.is_redeemable', true);
    }
}

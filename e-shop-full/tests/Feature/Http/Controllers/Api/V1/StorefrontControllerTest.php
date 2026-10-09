<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Mail\LowStockAlert;
use App\Mail\NewOrderReceived;
use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StorefrontControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function checkoutPayload(array $items, array $overrides = []): array
    {
        return [
            'customer' => ['first_name' => 'Fatou', 'last_name' => 'Sow', 'email' => 'fatou@example.com', 'phone' => '+221770000000'],
            'items' => $items,
            'payment_method' => 'mobile_money',
            ...$overrides,
        ];
    }

    public function test_products_lists_only_published_and_approved_products(): void
    {
        $shop = Shop::factory()->create();
        $visible = Product::factory()->for($shop)->create();
        Product::factory()->for($shop)->draft()->create();
        Product::factory()->for($shop)->awaitingReview()->create();
        Product::factory()->for($shop)->rejected()->create();

        $this->getJson("/api/v1/storefront/{$shop->slug}/products")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$visible->id]);
    }

    public function test_product_returns_404_for_rejected_product(): void
    {
        $product = Product::factory()->rejected()->create();

        $this->getJson("/api/v1/storefront/{$product->shop->slug}/products/{$product->slug}")->assertNotFound();
    }

    public function test_inactive_shop_returns_404(): void
    {
        $shop = Shop::factory()->inactive()->create();

        $this->getJson("/api/v1/storefront/{$shop->slug}")->assertNotFound();
    }

    public function test_checkout_prices_order_from_catalog_and_decrements_stock(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['price' => 4500, 'stock_quantity' => 5]);

        $response = $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1],
        ], ['payment_status' => 'paid']));

        $response->assertCreated()
            ->assertJsonPath('data.total', 9000)
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_method', 'mobile_money');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('customers', ['shop_id' => $shop->id, 'email' => 'fatou@example.com']);
    }

    public function test_checkout_rejects_quantity_above_stock_with_422(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 1]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 1]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_product_from_another_shop_or_not_approved_with_422(): void
    {
        $shop = Shop::factory()->create();
        $foreignProduct = Product::factory()->create();
        $hiddenProduct = Product::factory()->for($shop)->awaitingReview()->create();

        foreach ([$foreignProduct, $hiddenProduct] as $product) {
            $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
                ['product_id' => $product->id, 'quantity' => 1],
            ]))->assertUnprocessable()->assertJsonValidationErrors(['items' => 'Un des produits n\'est plus disponible.']);
        }
    }

    public function test_checkout_applies_valid_coupon_and_counts_its_use(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['price' => 10000]);
        $coupon = Coupon::create(['shop_id' => $shop->id, 'code' => 'PROMO10', 'type' => 'percentage', 'value' => 10, 'usage_limit' => 0, 'status' => 'active']);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['coupon_code' => 'promo10']))
            ->assertCreated()
            ->assertJsonPath('data.discount_amount', 1000)
            ->assertJsonPath('data.total', 9000);

        $this->assertSame(1, $coupon->refresh()->times_used);
    }

    public function test_checkout_rejects_expired_coupon_with_422(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create();
        Coupon::create(['shop_id' => $shop->id, 'code' => 'OLD', 'type' => 'fixed', 'value' => 500, 'ends_at' => now()->subDay(), 'status' => 'active']);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['coupon_code' => 'OLD']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['coupon_code' => 'Ce code promo est invalide ou expiré.']);
    }

    public function test_checkout_emails_the_customer_and_the_merchant(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 20]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertCreated();

        Mail::assertQueued(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('fatou@example.com'));
        Mail::assertQueued(NewOrderReceived::class, fn (NewOrderReceived $mail) => $mail->hasTo($shop->owner->email));
        Mail::assertNotQueued(LowStockAlert::class);
    }

    public function test_checkout_alerts_the_merchant_when_stock_drops_to_the_threshold(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 7]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        Mail::assertQueued(LowStockAlert::class, fn (LowStockAlert $mail) => $mail->product->is($product) && $mail->hasTo($shop->owner->email));
    }

    public function test_recommendations_rank_products_bought_together_first(): void
    {
        $shop = Shop::factory()->create();
        [$viewed, $boughtTogether, $other] = Product::factory()->for($shop)->count(3)->create();
        $order = Order::factory()->for($shop)->create();
        foreach ([$viewed, $boughtTogether] as $product) {
            $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1]);
        }

        $this->getJson("/api/v1/storefront/{$shop->slug}/products/{$viewed->slug}/recommendations")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$boughtTogether->id, $other->id]);
    }

    public function test_recommendations_exclude_hidden_and_out_of_stock_products(): void
    {
        $shop = Shop::factory()->create();
        $viewed = Product::factory()->for($shop)->create();
        Product::factory()->for($shop)->rejected()->create();
        Product::factory()->for($shop)->create(['stock_quantity' => 0]);

        $this->getJson("/api/v1/storefront/{$shop->slug}/products/{$viewed->slug}/recommendations")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_order_tracking_returns_order_when_email_matches(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-TRACK123']);

        $this->getJson("/api/v1/storefront/{$order->shop->slug}/orders/ord-track123?email=".strtoupper($order->customer->email))
            ->assertOk()
            ->assertJsonPath('data.order_number', 'ORD-TRACK123');
    }

    public function test_order_tracking_returns_404_when_email_does_not_match(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/storefront/{$order->shop->slug}/orders/{$order->order_number}?email=intrus@example.com")
            ->assertNotFound();
    }
}

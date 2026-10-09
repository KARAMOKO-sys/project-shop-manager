<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_update_moves_order_along_allowed_transition_and_emails_the_customer(): void
    {
        Mail::fake();
        $user = Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->for(Shop::factory()->for($user, 'owner'))->create();

        $this->patchJson("/api/v1/shops/{$order->shop_id}/orders/{$order->id}", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_status', 'paid');

        Mail::assertQueued(OrderStatusUpdated::class, fn (OrderStatusUpdated $mail) => $mail->hasTo($order->customer->email));
    }

    public function test_update_rejects_skipping_from_received_to_delivered_with_422(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->for(Shop::factory()->for($user, 'owner'))->create();

        $this->patchJson("/api/v1/shops/{$order->shop_id}/orders/{$order->id}", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_cancelling_an_order_restores_stock(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->for($user, 'owner')->create();
        $product = Product::factory()->for($shop)->create(['stock_quantity' => 3]);
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::Processing]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 1000, 'total_price' => 2000]);

        $this->patchJson("/api/v1/shops/{$shop->id}/orders/{$order->id}", ['status' => 'cancelled'])->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);
    }

    public function test_index_forbids_another_merchants_orders_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->create();

        $this->getJson("/api/v1/shops/{$order->shop_id}/orders")->assertForbidden();
    }
}

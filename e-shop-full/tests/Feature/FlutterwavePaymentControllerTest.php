<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FlutterwavePaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_webhook_verifies_flutterwave_transaction_before_marking_order_paid(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.flutterwave.com/v3/transactions/12345/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 12345,
                    'tx_ref' => 'ORD-ABCDEFGH',
                    'status' => 'successful',
                    'amount' => 9000,
                    'currency' => 'XOF',
                ],
            ]),
        ]);
        Mail::fake();

        $shop = Shop::factory()->create([
            'flutterwave_secret_key' => 'FLWSECK_TEST-merchant-secret',
            'flutterwave_webhook_secret_hash' => 'merchant-webhook-secret',
            'flutterwave_enabled' => true,
        ]);
        $order = Order::factory()->for($shop)->create([
            'order_number' => 'ORD-ABCDEFGH',
            'status' => OrderStatus::Received,
            'payment_method' => PaymentMethod::Flutterwave,
            'payment_status' => 'pending',
            'total' => 9000,
            'currency' => 'XOF',
        ]);
        $order->payment()->create([
            'provider' => 'flutterwave',
            'status' => 'pending',
            'amount' => 9000,
            'currency' => 'XOF',
            'transaction_reference' => $order->order_number,
        ]);

        $this->postJson('/api/v1/payments/flutterwave/webhook', [
            'data' => ['id' => 12345, 'tx_ref' => $order->order_number],
        ], ['verif-hash' => 'merchant-webhook-secret'])
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'paid',
            'provider_transaction_id' => '12345',
        ]);
        Mail::assertQueued(OrderStatusUpdated::class, fn (OrderStatusUpdated $mail): bool => $mail->hasTo($order->customer->email));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.flutterwave.com/v3/transactions/12345/verify'
            && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-merchant-secret'));
    }

    public function test_webhook_returns_401_for_invalid_secret_hash_without_verifying_transaction(): void
    {
        Http::preventStrayRequests();
        Mail::fake();

        $shop = Shop::factory()->create([
            'flutterwave_secret_key' => 'FLWSECK_TEST-merchant-secret',
            'flutterwave_webhook_secret_hash' => 'merchant-webhook-secret',
            'flutterwave_enabled' => true,
        ]);
        $order = Order::factory()->for($shop)->create([
            'order_number' => 'ORD-ABCDEFGH',
            'payment_method' => PaymentMethod::Flutterwave,
        ]);
        $order->payment()->create([
            'provider' => 'flutterwave',
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
            'transaction_reference' => $order->order_number,
        ]);

        $this->postJson('/api/v1/payments/flutterwave/webhook', [
            'data' => ['id' => 12345, 'tx_ref' => $order->order_number],
        ], ['verif-hash' => 'wrong-secret'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
    }

    public function test_webhook_does_not_mark_order_paid_when_verified_amount_differs(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.flutterwave.com/v3/transactions/12345/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 12345,
                    'tx_ref' => 'ORD-ABCDEFGH',
                    'status' => 'successful',
                    'amount' => 1,
                    'currency' => 'XOF',
                ],
            ]),
        ]);
        Mail::fake();

        $shop = Shop::factory()->create([
            'flutterwave_secret_key' => 'FLWSECK_TEST-merchant-secret',
            'flutterwave_webhook_secret_hash' => 'merchant-webhook-secret',
            'flutterwave_enabled' => true,
        ]);
        $order = Order::factory()->for($shop)->create([
            'order_number' => 'ORD-ABCDEFGH',
            'status' => OrderStatus::Received,
            'payment_method' => PaymentMethod::Flutterwave,
            'total' => 9000,
        ]);
        $order->payment()->create([
            'provider' => 'flutterwave',
            'status' => 'pending',
            'amount' => 9000,
            'currency' => 'XOF',
            'transaction_reference' => $order->order_number,
        ]);

        $this->postJson('/api/v1/payments/flutterwave/webhook', [
            'data' => ['id' => 12345, 'tx_ref' => $order->order_number],
        ], ['verif-hash' => 'merchant-webhook-secret'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'received', 'payment_status' => 'pending']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
    }

    public function test_callback_verifies_transaction_and_redirects_to_order_tracking(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.flutterwave.com/v3/transactions/12345/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 12345,
                    'tx_ref' => 'ORD-ABCDEFGH',
                    'status' => 'successful',
                    'amount' => 9000,
                    'currency' => 'XOF',
                ],
            ]),
        ]);
        Mail::fake();

        $shop = Shop::factory()->create([
            'flutterwave_secret_key' => 'FLWSECK_TEST-merchant-secret',
            'flutterwave_webhook_secret_hash' => 'merchant-webhook-secret',
            'flutterwave_enabled' => true,
        ]);
        $order = Order::factory()->for($shop)->create([
            'order_number' => 'ORD-ABCDEFGH',
            'status' => OrderStatus::Received,
            'payment_method' => PaymentMethod::Flutterwave,
            'payment_status' => 'pending',
            'total' => 9000,
            'currency' => 'XOF',
        ]);
        $order->payment()->create([
            'provider' => 'flutterwave',
            'status' => 'pending',
            'amount' => 9000,
            'currency' => 'XOF',
            'transaction_reference' => $order->order_number,
        ]);

        $this->get('/api/v1/payments/flutterwave/callback?'.http_build_query([
            'tx_ref' => $order->order_number,
            'transaction_id' => '12345',
            'status' => 'successful',
        ]))
            ->assertRedirect(rtrim(config('shopai.frontend_url'), '/')."/boutique/{$shop->slug}/suivi?commande={$order->order_number}");

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
    }
}

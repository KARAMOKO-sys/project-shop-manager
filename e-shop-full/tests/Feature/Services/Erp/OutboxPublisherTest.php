<?php

namespace Tests\Feature\Services\Erp;

use App\Models\OutboxMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Erp\EventOutbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutboxPublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENDPOINT = 'https://esb.example.test/shopai/events';

    private function configureEsb(int $maxAttempts = 10): void
    {
        config([
            'erp.esb.endpoint' => self::ENDPOINT,
            'erp.esb.secret' => 'top-secret',
            'erp.esb.max_attempts' => $maxAttempts,
        ]);
    }

    public function test_publishes_pending_messages_with_hmac_signature(): void
    {
        $this->configureEsb();
        Http::fake([self::ENDPOINT => Http::response(['ok' => true])]);
        $message = app(EventOutbox::class)->record('invoice.paid', ['invoice_id' => 7, 'total' => 11800]);

        $this->artisan('erp:outbox:publish')->assertSuccessful();

        Http::assertSent(function (Request $request) use ($message): bool {
            $expected = 'sha256='.hash_hmac('sha256', $request->header('X-ShopAI-Timestamp')[0].'.'.$request->body(), 'top-secret');

            return $request->url() === self::ENDPOINT
                && $request->header('X-ShopAI-Signature')[0] === $expected
                && $request->header('X-ShopAI-Event-Id')[0] === $message->uuid
                && $request->header('X-ShopAI-Event')[0] === 'invoice.paid'
                && $request['event'] === 'invoice.paid'
                && $request['data']['total'] === 11800;
        });

        $message->refresh();
        $this->assertSame(OutboxMessage::STATUS_PUBLISHED, $message->status);
        $this->assertNotNull($message->published_at);
        $this->assertSame(1, $message->attempts);
    }

    public function test_failed_delivery_is_retried_later_with_backoff(): void
    {
        $this->configureEsb();
        Http::fake([self::ENDPOINT => Http::response('down', 503)]);
        $message = app(EventOutbox::class)->record('order.placed', ['order_id' => 1]);

        $this->artisan('erp:outbox:publish')->assertSuccessful();

        $message->refresh();
        $this->assertSame(OutboxMessage::STATUS_PENDING, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('HTTP 503', $message->last_error);
        $this->assertTrue($message->available_at->isFuture());

        Http::fake([self::ENDPOINT => Http::response(['ok' => true])]);
        $this->artisan('erp:outbox:publish')->assertSuccessful();
        $this->assertSame(OutboxMessage::STATUS_PENDING, $message->fresh()->status, 'Not retried before its backoff delay.');
    }

    public function test_message_is_marked_failed_after_max_attempts(): void
    {
        $this->configureEsb(maxAttempts: 1);
        Http::fake([self::ENDPOINT => Http::response('error', 500)]);
        $message = app(EventOutbox::class)->record('order.placed', ['order_id' => 1]);

        $this->artisan('erp:outbox:publish')->assertSuccessful();

        $this->assertSame(OutboxMessage::STATUS_FAILED, $message->fresh()->status);
    }

    public function test_nothing_is_sent_without_esb_configuration(): void
    {
        config(['erp.esb.endpoint' => null, 'erp.esb.secret' => null]);
        Http::fake();
        $message = app(EventOutbox::class)->record('order.placed', ['order_id' => 1]);

        $this->artisan('erp:outbox:publish')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(OutboxMessage::STATUS_PENDING, $message->fresh()->status);
    }

    public function test_storefront_checkout_records_order_placed_event(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['price' => 5000, 'stock_quantity' => 5]);

        $this->postJson("/api/v1/storefront/{$shop->slug}/checkout", [
            'customer' => ['first_name' => 'Fatou', 'last_name' => 'Sow', 'email' => 'fatou@example.com', 'phone' => '+221770000000'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated();

        $message = OutboxMessage::query()->where('event', 'order.placed')->sole();
        $this->assertSame($shop->id, $message->shop_id);
        $this->assertSame(10000.0, (float) $message->payload['total']);
        $this->assertEquals([['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 5000]], $message->payload['items']);
        $this->assertArrayNotHasKey('delivery_address', $message->payload);
    }
}

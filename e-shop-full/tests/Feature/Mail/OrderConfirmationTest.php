<?php

namespace Tests\Feature\Mail;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_items_total_and_prefilled_tracking_link(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-MAIL0001', 'total' => 12500]);
        $order->items()->create(['product_id' => Product::factory()->for($order->shop)->create()->id, 'product_name' => 'Robe wax', 'quantity' => 2, 'unit_price' => 6250, 'total_price' => 12500]);

        $mail = new OrderConfirmation($order->load('shop', 'customer', 'items'));

        $mail->assertHasSubject("Votre commande ORD-MAIL0001 chez {$order->shop->name}");
        $mail->assertSeeInText('Robe wax');
        $mail->assertSeeInText('12 500 XOF');
        $mail->assertSeeInHtml("/boutique/{$order->shop->slug}/suivi?commande=ORD-MAIL0001", false);
    }
}

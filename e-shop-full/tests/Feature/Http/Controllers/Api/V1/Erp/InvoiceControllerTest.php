<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Enums\OrderStatus;
use App\Models\Customer;
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

class InvoiceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoicingShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create(['tax_rate' => 18]);
        app(ModuleRegistry::class)->install($shop, 'invoicing');

        return $shop;
    }

    /**
     * Order of 2 × 5 000 + 1 000 delivery − 1 000 discount = 10 000 XOF.
     */
    private function orderFor(Shop $shop): Order
    {
        $product = Product::factory()->for($shop)->create(['price' => 5000]);
        $order = Order::factory()->for($shop)->create(['subtotal' => 10000, 'shipping_amount' => 1000, 'discount_amount' => 1000, 'total' => 10000]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 5000, 'total_price' => 10000]);

        return $order;
    }

    private function base(Shop $shop): string
    {
        return "/api/v1/shops/{$shop->id}/erp";
    }

    public function test_invoice_from_order_copies_items_delivery_and_discount_and_extracts_tax(): void
    {
        $shop = $this->invoicingShop();
        $order = $this->orderFor($shop);

        $this->postJson("{$this->base($shop)}/invoices/from-order/{$order->id}")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.order.order_number', $order->order_number)
            ->assertJsonPath('data.total', 10000)
            ->assertJsonPath('data.tax_amount', 1525.42)
            ->assertJsonPath('data.subtotal', 8474.58)
            ->assertJsonPath('data.amount_due', 10000)
            ->assertJsonCount(3, 'data.lines')
            ->assertJsonPath('data.lines.1.description', 'Frais de livraison')
            ->assertJsonPath('data.lines.2.unit_price', -1000)
            ->assertJsonPath('data.lines.0.tax_rate', 18);
    }

    public function test_an_order_cannot_be_invoiced_twice(): void
    {
        $shop = $this->invoicingShop();
        $order = $this->orderFor($shop);

        $this->postJson("{$this->base($shop)}/invoices/from-order/{$order->id}")->assertCreated();
        $this->postJson("{$this->base($shop)}/invoices/from-order/{$order->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');

        $this->getJson("{$this->base($shop)}/invoiceable-orders")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_invoiceable_orders_excludes_cancelled_orders(): void
    {
        $shop = $this->invoicingShop();
        $open = $this->orderFor($shop);
        Order::factory()->for($shop)->create(['status' => OrderStatus::Cancelled]);

        $this->getJson("{$this->base($shop)}/invoiceable-orders")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$open->id]);
    }

    public function test_posting_assigns_gap_free_yearly_numbers(): void
    {
        $shop = $this->invoicingShop();
        $year = now()->format('Y');
        $first = Invoice::factory()->for($shop)->create();
        $second = Invoice::factory()->for($shop)->create();
        $first->lines()->create(['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 18, 'total' => 100]);
        $second->lines()->create(['description' => 'B', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 18, 'total' => 100]);

        $this->postJson("{$this->base($shop)}/invoices/{$first->id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.number', "FAC-{$year}-00001");
        $this->postJson("{$this->base($shop)}/invoices/{$second->id}/post")->assertJsonPath('data.number', "FAC-{$year}-00002");
        $this->postJson("{$this->base($shop)}/invoices/{$first->id}/post")->assertUnprocessable();

        $this->assertDatabaseHas('outbox_messages', ['event' => 'invoice.posted', 'shop_id' => $shop->id]);
    }

    public function test_store_creates_manual_draft_invoice(): void
    {
        $shop = $this->invoicingShop();

        $this->postJson("{$this->base($shop)}/invoices", [
            'customer_name' => 'Awa Diallo',
            'lines' => [['description' => 'Conseil', 'quantity' => 2, 'unit_price' => 5900]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total', 11800)
            ->assertJsonPath('data.tax_amount', 1800)
            ->assertJsonPath('data.subtotal', 10000);
    }

    public function test_store_rejects_customer_and_product_of_another_shop(): void
    {
        $shop = $this->invoicingShop();
        $foreignCustomer = Customer::factory()->create();
        $foreignProduct = Product::factory()->create();

        $this->postJson("{$this->base($shop)}/invoices", [
            'customer_id' => $foreignCustomer->id,
            'customer_name' => 'X',
            'lines' => [['product_id' => $foreignProduct->id, 'description' => 'Y', 'quantity' => 1, 'unit_price' => 10]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'lines.0.product_id']);
    }

    public function test_partial_then_full_payment_and_overpayment_is_rejected(): void
    {
        $shop = $this->invoicingShop();
        $order = $this->orderFor($shop);
        $invoiceId = $this->postJson("{$this->base($shop)}/invoices/from-order/{$order->id}")->json('data.id');
        $this->postJson("{$this->base($shop)}/invoices/{$invoiceId}/post")->assertOk();

        $this->postJson("{$this->base($shop)}/invoices/{$invoiceId}/payments", ['amount' => 4000, 'method' => 'mobile_money', 'reference' => 'OM-123'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_due', 6000)
            ->assertJsonCount(1, 'data.payments');

        $this->postJson("{$this->base($shop)}/invoices/{$invoiceId}/payments", ['amount' => 7000, 'method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->postJson("{$this->base($shop)}/invoices/{$invoiceId}/payments", ['amount' => 6000, 'method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_due', 0);

        $this->assertDatabaseHas('outbox_messages', ['event' => 'invoice.paid', 'shop_id' => $shop->id]);
        $this->postJson("{$this->base($shop)}/invoices/{$invoiceId}/cancel")->assertUnprocessable();
    }

    public function test_payment_on_draft_invoice_is_rejected(): void
    {
        $shop = $this->invoicingShop();
        $invoice = Invoice::factory()->for($shop)->create();

        $this->postJson("{$this->base($shop)}/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_posted_invoice_cannot_be_edited(): void
    {
        $shop = $this->invoicingShop();
        $invoice = Invoice::factory()->for($shop)->posted()->create();

        $this->putJson("{$this->base($shop)}/invoices/{$invoice->id}", ['customer_name' => 'Autre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_draft_invoice_can_be_updated_and_cancelled(): void
    {
        $shop = $this->invoicingShop();
        $invoice = Invoice::factory()->for($shop)->create();

        $this->putJson("{$this->base($shop)}/invoices/{$invoice->id}", [
            'customer_name' => 'Nouveau client',
            'lines' => [['description' => 'Ligne', 'quantity' => 1, 'unit_price' => 1180, 'tax_rate' => 18]],
        ])
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'Nouveau client')
            ->assertJsonPath('data.total', 1180);

        $this->postJson("{$this->base($shop)}/invoices/{$invoice->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $shop = $this->invoicingShop();
        Invoice::factory()->for($shop)->create(['customer_name' => 'Moussa']);
        $posted = Invoice::factory()->for($shop)->posted()->create(['customer_name' => 'Aminata']);

        $this->getJson("{$this->base($shop)}/invoices?status=posted")->assertOk()->assertJsonPath('data.*.id', [$posted->id]);
        $this->getJson("{$this->base($shop)}/invoices?search=Amin")->assertOk()->assertJsonPath('data.*.id', [$posted->id]);
    }

    public function test_invoice_of_another_shop_returns_404(): void
    {
        $shop = $this->invoicingShop();
        $foreign = Invoice::factory()->create();

        $this->getJson("{$this->base($shop)}/invoices/{$foreign->id}")->assertNotFound();
    }

    public function test_routes_return_403_when_invoicing_is_not_installed(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->getJson("{$this->base($shop)}/invoices")->assertForbidden();
    }
}

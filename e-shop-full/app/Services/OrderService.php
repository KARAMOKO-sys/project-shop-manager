<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Mail\LowStockAlert;
use App\Mail\NewOrderReceived;
use App\Mail\OrderConfirmation;
use App\Mail\OrderStatusUpdated;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Erp\EventOutbox;
use App\Services\Erp\InventoryService;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private ModuleRegistry $modules,
        private InventoryService $inventory,
        private EventOutbox $outbox,
    ) {}

    /**
     * Place an order from the storefront. Prices, discounts and stock are always
     * computed server-side from the catalog, never trusted from the client.
     *
     * @param  array{customer: array{first_name: string, last_name: string, email: string, phone?: ?string}, items: list<array{product_id: int, quantity: int}>, coupon_code?: ?string, payment_method: string, notes?: ?string}  $payload
     */
    public function checkout(Shop $shop, array $payload): Order
    {
        return DB::transaction(function () use ($shop, $payload) {
            $quantities = collect($payload['items'])
                ->groupBy('product_id')
                ->map(fn ($lines) => $lines->sum('quantity'));

            $products = $shop->products()
                ->publiclyVisible()
                ->whereKey($quantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0.0;

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'Un des produits n\'est plus disponible.']);
                }

                if ($product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => "Stock insuffisant pour « {$product->name} » ({$product->stock_quantity} disponible(s))."]);
                }

                $lineTotal = round((float) $product->price * $quantity, 2);
                $subtotal += $lineTotal;
                $lines[] = ['product' => $product, 'quantity' => $quantity, 'total' => $lineTotal];
            }

            $coupon = $this->resolveCoupon($shop, $payload['coupon_code'] ?? null, $subtotal);
            $discount = $coupon?->discountFor($subtotal) ?? 0.0;

            $deliveryMethod = DeliveryMethod::tryFrom($payload['delivery_method'] ?? '') ?? DeliveryMethod::Delivery;
            $shipping = $shop->deliveryFeeFor($deliveryMethod, $subtotal - $discount);

            $customer = $shop->customers()->updateOrCreate(
                ['email' => Str::lower($payload['customer']['email'])],
                [
                    'first_name' => $payload['customer']['first_name'],
                    'last_name' => $payload['customer']['last_name'],
                    'phone' => $payload['customer']['phone'] ?? null,
                ],
            );

            $order = $shop->orders()->create([
                'customer_id' => $customer->id,
                'coupon_id' => $coupon?->id,
                'order_number' => $this->newOrderNumber(),
                'status' => OrderStatus::Received,
                'subtotal' => $subtotal,
                'shipping_amount' => $shipping,
                'discount_amount' => $discount,
                'total' => $subtotal - $discount + $shipping,
                'currency' => $shop->currency,
                'payment_method' => $payload['payment_method'],
                'payment_status' => 'pending',
                'delivery_method' => $deliveryMethod,
                'delivery_address' => $deliveryMethod === DeliveryMethod::Delivery ? ($payload['delivery_address'] ?? null) : null,
                'delivery_city' => $deliveryMethod === DeliveryMethod::Delivery ? ($payload['delivery_city'] ?? null) : null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $tracksInventory = $this->modules->isInstalled($shop, 'inventory');

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $line['total'],
                ]);

                $stockBefore = $product->stock_quantity;

                if ($tracksInventory) {
                    $this->inventory->recordSale($order, $product, $line['quantity']);
                } else {
                    $product->decrement('stock_quantity', $line['quantity']);

                    if ($product->stock_quantity <= 0) {
                        $product->update(['stock_status' => 'out_of_stock']);
                    }
                }

                if ($stockBefore > AnalyticsService::LOW_STOCK_THRESHOLD && $product->stock_quantity <= AnalyticsService::LOW_STOCK_THRESHOLD) {
                    Mail::to($shop->owner)->send(new LowStockAlert($product));
                }
            }

            $coupon?->increment('times_used');

            $order->load('shop', 'customer', 'items');

            $this->outbox->record('order.placed', $this->eventPayload($order), $shop);

            Mail::to($customer->email)->send(new OrderConfirmation($order));
            Mail::to($shop->owner)->send(new NewOrderReceived($order));

            return $order;
        });
    }

    public function changeStatus(Order $order, OrderStatus $status, ?string $paymentStatus = null): Order
    {
        if (! $order->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "Impossible de passer une commande de « {$order->status->value} » à « {$status->value} ».",
            ]);
        }

        DB::transaction(function () use ($order, $status, $paymentStatus) {
            if ($status === OrderStatus::Cancelled) {
                $tracksInventory = $this->modules->isInstalled($order->shop, 'inventory');

                foreach ($order->items as $item) {
                    if ($tracksInventory && $item->product) {
                        $this->inventory->recordSaleReturn($order, $item->product, $item->quantity);
                    } else {
                        Product::whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
                    }
                }

                if ($order->coupon_id) {
                    Coupon::query()
                        ->whereKey($order->coupon_id)
                        ->where('times_used', '>', 0)
                        ->decrement('times_used');
                }
            }

            $previousStatus = $order->status;

            $order->update([
                'status' => $status,
                'payment_status' => $paymentStatus ?? ($status === OrderStatus::Paid ? 'paid' : $order->payment_status),
            ]);

            $this->outbox->record('order.status_changed', [
                ...$this->eventPayload($order),
                'previous_status' => $previousStatus->value,
            ], $order->shop);

            Mail::to($order->customer->email)->send(new OrderStatusUpdated($order->load('shop', 'customer')));
        });

        return $order;
    }

    /**
     * Data sent to the ESB about an order: identifiers and amounts, no address.
     *
     * @return array<string, mixed>
     */
    private function eventPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'shop_id' => $order->shop_id,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method?->value,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->all(),
        ];
    }

    private function resolveCoupon(Shop $shop, ?string $code, float $subtotal): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = $shop->coupons()->where('code', Str::upper($code))->lockForUpdate()->first();

        if (! $coupon || ! $coupon->isRedeemableFor($subtotal)) {
            throw ValidationException::withMessages(['coupon_code' => 'Ce code promo est invalide ou expiré.']);
        }

        return $coupon;
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'ORD-'.Str::upper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}

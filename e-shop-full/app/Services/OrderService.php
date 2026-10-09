<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\LowStockAlert;
use App\Mail\NewOrderReceived;
use App\Mail\OrderConfirmation;
use App\Mail\OrderStatusUpdated;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
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
                'shipping_amount' => 0,
                'discount_amount' => $discount,
                'total' => $subtotal - $discount,
                'currency' => $shop->currency,
                'payment_method' => $payload['payment_method'],
                'payment_status' => 'pending',
                'notes' => $payload['notes'] ?? null,
            ]);

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
                $product->decrement('stock_quantity', $line['quantity']);

                if ($product->stock_quantity <= 0) {
                    $product->update(['stock_status' => 'out_of_stock']);
                }

                if ($stockBefore > AnalyticsService::LOW_STOCK_THRESHOLD && $product->stock_quantity <= AnalyticsService::LOW_STOCK_THRESHOLD) {
                    Mail::to($shop->owner)->send(new LowStockAlert($product));
                }
            }

            $coupon?->increment('times_used');

            $order->load('shop', 'customer', 'items');

            Mail::to($customer->email)->send(new OrderConfirmation($order));
            Mail::to($shop->owner)->send(new NewOrderReceived($order));

            return $order;
        });
    }

    public function changeStatus(Order $order, OrderStatus $status): Order
    {
        if (! $order->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "Impossible de passer une commande de « {$order->status->value} » à « {$status->value} ».",
            ]);
        }

        DB::transaction(function () use ($order, $status) {
            if ($status === OrderStatus::Cancelled) {
                foreach ($order->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
                }
            }

            $order->update([
                'status' => $status,
                'payment_status' => $status === OrderStatus::Paid ? 'paid' : $order->payment_status,
            ]);

            Mail::to($order->customer->email)->send(new OrderStatusUpdated($order->load('shop', 'customer')));
        });

        return $order;
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

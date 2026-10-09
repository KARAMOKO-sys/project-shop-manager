<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(2_000, 80_000);

        return [
            'shop_id' => Shop::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['shop_id' => $attributes['shop_id']])->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Received,
            'subtotal' => $total,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
            'currency' => 'XOF',
            'payment_method' => PaymentMethod::CashOnDelivery,
            'payment_status' => 'pending',
        ];
    }
}

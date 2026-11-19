<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(5_000, 100_000);

        return [
            'shop_id' => Shop::factory(),
            'status' => InvoiceStatus::Draft,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'issue_date' => today(),
            'due_date' => today()->addDays(15),
            'subtotal' => round($total / 1.18, 2),
            'tax_amount' => round($total - $total / 1.18, 2),
            'total' => $total,
            'amount_paid' => 0,
            'currency' => 'XOF',
        ];
    }

    /**
     * A validated invoice waiting for payment.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Posted,
            'number' => 'FAC-'.now()->format('Y').'-'.fake()->unique()->numerify('9####'),
            'posted_at' => now(),
        ]);
    }
}

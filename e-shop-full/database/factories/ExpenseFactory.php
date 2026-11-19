<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'label' => fake()->sentence(3),
            'amount' => fake()->numberBetween(1_000, 200_000),
            'currency' => 'XOF',
            'spent_at' => today(),
            'payment_method' => 'cash',
        ];
    }
}

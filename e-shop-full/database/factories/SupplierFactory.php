<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
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
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+22507'.fake()->numerify('########'),
            'address' => fake()->streetAddress(),
            'payment_terms_days' => 30,
            'lead_time_days' => 7,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'shop_id' => Shop::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(1_000, 50_000),
            'stock_quantity' => 20,
            'stock_status' => 'in_stock',
            'status' => ProductStatus::Published,
            'moderation_status' => ModerationStatus::Approved,
            'moderation_risk_score' => 0,
            'moderation_engine' => 'local',
            'moderated_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
        ]);
    }

    public function awaitingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => ModerationStatus::PendingReview,
            'moderation_risk_score' => 50,
            'moderation_reasons' => ['Réduction affichée irréaliste.'],
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => ModerationStatus::Rejected,
            'moderation_risk_score' => 90,
            'moderation_reasons' => ['Vente d\'armes ou de munitions interdite.'],
        ]);
    }
}

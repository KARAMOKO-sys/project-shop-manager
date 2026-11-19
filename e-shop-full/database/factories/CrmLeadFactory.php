<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\CrmLead;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmLead>
 */
class CrmLeadFactory extends Factory
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
            'title' => fake()->sentence(3),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'stage' => LeadStage::New,
            'source' => LeadSource::WhatsApp,
            'expected_revenue' => fake()->numberBetween(10_000, 500_000),
            'probability' => LeadStage::New->defaultProbability(),
        ];
    }

    public function inStage(LeadStage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => $stage,
            'probability' => $stage->defaultProbability(),
            'closed_at' => $stage->isClosed() ? now() : null,
        ]);
    }
}

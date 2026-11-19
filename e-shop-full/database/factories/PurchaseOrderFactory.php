<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
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
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['shop_id' => $attributes['shop_id']])->id,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['shop_id' => $attributes['shop_id']])->id,
            'number' => 'PO-'.Str::upper(Str::random(8)),
            'status' => PurchaseOrderStatus::Draft,
            'total' => 0,
            'currency' => 'XOF',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}

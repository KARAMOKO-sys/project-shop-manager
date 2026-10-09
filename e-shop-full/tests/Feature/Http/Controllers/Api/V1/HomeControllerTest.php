<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_features_only_public_products_and_lists_templates(): void
    {
        $visible = Product::factory()->create(['is_featured' => true]);
        Product::factory()->rejected()->create();
        Product::factory()->for(Shop::factory()->inactive())->create();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.featured_products.*.id', [$visible->id])
            ->assertJsonPath('data.stats.products', 1)
            ->assertJsonCount(count(config('shopai.templates')), 'data.templates')
            ->assertJsonPath('data.templates.0.key', 'minimal')
            ->assertJsonPath('data.templates.0.theme.format', 'classic')
            ->assertJsonPath('data.templates.0.ideal_for.0', 'Tout type de boutique');
    }
}

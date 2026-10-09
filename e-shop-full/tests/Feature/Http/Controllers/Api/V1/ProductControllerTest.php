<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function merchantShop(): Shop
    {
        $user = Sanctum::actingAs(User::factory()->create());

        return Shop::factory()->for($user, 'owner')->create();
    }

    public function test_store_approves_legitimate_product_and_makes_it_public(): void
    {
        $shop = $this->merchantShop();

        $response = $this->postJson("/api/v1/shops/{$shop->id}/products", [
            'name' => 'Beurre de karité brut',
            'description' => 'Beurre de karité non raffiné, extrait à froid par une coopérative.',
            'price' => 3500,
            'stock_quantity' => 10,
            'tags' => ['karite'],
            'status' => 'published',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'beurre-de-karite-brut')
            ->assertJsonPath('data.moderation.status', 'approved')
            ->assertJsonPath('data.moderation.engine', 'local')
            ->assertJsonPath('data.is_public', true);
    }

    public function test_store_rejects_counterfeit_listing_and_keeps_it_hidden(): void
    {
        $shop = $this->merchantShop();

        $response = $this->postJson("/api/v1/shops/{$shop->id}/products", [
            'name' => 'Montre Rolex Daytona',
            'description' => 'Réplique qualité 1:1, contactez-moi sur WhatsApp au +225 07 07 07 07 07.',
            'price' => 20000,
            'status' => 'published',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.moderation.status', 'rejected')
            ->assertJsonPath('data.is_public', false);
        $this->assertGreaterThanOrEqual(70, $response->json('data.moderation.risk_score'));
    }

    public function test_update_of_a_moderated_field_triggers_a_new_review(): void
    {
        $shop = $this->merchantShop();
        $product = Product::factory()->for($shop)->create(['price' => 5000]);

        $this->putJson("/api/v1/shops/{$shop->id}/products/{$product->id}", [
            'compare_at_price' => 90000,
        ])
            ->assertOk()
            ->assertJsonPath('data.moderation.status', 'pending_review')
            ->assertJsonPath('data.is_public', false);
    }

    public function test_update_of_stock_only_keeps_the_existing_verdict(): void
    {
        $shop = $this->merchantShop();
        $product = Product::factory()->for($shop)->awaitingReview()->create();

        $this->putJson("/api/v1/shops/{$shop->id}/products/{$product->id}", ['stock_quantity' => 0])
            ->assertOk()
            ->assertJsonPath('data.moderation.status', 'pending_review');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_status' => 'out_of_stock']);
    }

    public function test_store_rejects_category_from_another_shop_with_422(): void
    {
        $shop = $this->merchantShop();
        $foreignCategory = Shop::factory()->create()->categories()->create(['name' => 'Autre', 'slug' => 'autre']);

        $this->postJson("/api/v1/shops/{$shop->id}/products", [
            'name' => 'Produit',
            'price' => 1000,
            'category_id' => $foreignCategory->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_store_requires_name_and_price(): void
    {
        $shop = $this->merchantShop();

        $this->postJson("/api/v1/shops/{$shop->id}/products", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_product_of_another_shop_returns_404_through_scoped_binding(): void
    {
        $shop = $this->merchantShop();
        $foreignProduct = Product::factory()->create();

        $this->getJson("/api/v1/shops/{$shop->id}/products/{$foreignProduct->id}")->assertNotFound();
        $this->deleteJson("/api/v1/shops/{$shop->id}/products/{$foreignProduct->id}")->assertNotFound();
        $this->assertModelExists($foreignProduct);
    }

    public function test_store_forbids_adding_products_to_another_merchants_shop_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->create();

        $this->postJson("/api/v1/shops/{$shop->id}/products", ['name' => 'Produit', 'price' => 1000])->assertForbidden();
    }

    public function test_moderate_reruns_the_agent_on_demand(): void
    {
        $shop = $this->merchantShop();
        $product = Product::factory()->for($shop)->create([
            'moderation_status' => ModerationStatus::Pending,
            'description' => 'Un joli panier tressé à la main par des artisans locaux.',
        ]);

        $this->postJson("/api/v1/shops/{$shop->id}/products/{$product->id}/moderate")
            ->assertOk()
            ->assertJsonPath('data.moderation.status', 'approved');
    }
}

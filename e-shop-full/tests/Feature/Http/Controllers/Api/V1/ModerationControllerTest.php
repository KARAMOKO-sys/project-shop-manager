<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_lists_products_awaiting_human_review_for_admins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $flagged = Product::factory()->awaitingReview()->create();
        Product::factory()->create();

        $this->getJson('/api/v1/admin/moderation')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$flagged->id]);
    }

    public function test_index_forbids_merchants_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/moderation')->assertForbidden();
    }

    public function test_admin_approval_publishes_the_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->awaitingReview()->create();

        $this->postJson("/api/v1/admin/moderation/{$product->id}", ['decision' => 'approve', 'note' => 'Prix vérifié avec le marchand.'])
            ->assertOk()
            ->assertJsonPath('data.moderation.status', 'approved')
            ->assertJsonPath('data.moderation.engine', 'human')
            ->assertJsonPath('data.is_public', true);
    }
}

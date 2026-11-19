<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModeratorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_creates_a_moderator_and_receives_a_generated_password_once(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/moderators', ['name' => 'Koffi Yao', 'email' => 'Koffi@Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'koffi@example.com')
            ->assertJsonPath('data.role', 'moderator')
            ->assertJsonPath('data.created_by', $admin->name);

        $password = $response->json('meta.password');
        $this->assertGreaterThanOrEqual(14, strlen($password));
        $this->assertTrue(Hash::check($password, User::firstWhere('email', 'koffi@example.com')->password));

        $this->getJson('/api/v1/admin/moderators')->assertOk()->assertJsonMissingPath('data.0.password');
    }

    public function test_merchants_and_moderators_cannot_manage_moderators(): void
    {
        foreach ([User::factory()->create(), User::factory()->moderator()->create()] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/admin/moderators')->assertForbidden();
            $this->postJson('/api/v1/admin/moderators', ['name' => 'Intrus', 'email' => 'intrus@example.com'])->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.com']);
    }

    public function test_moderator_reviews_products_but_cannot_change_platform_settings(): void
    {
        $moderator = User::factory()->moderator()->create();
        $product = Product::factory()->awaitingReview()->create();
        Sanctum::actingAs($moderator);

        $this->postJson("/api/v1/admin/moderation/{$product->id}", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.moderation.reviewed_by', $moderator->name);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'moderated_by' => $moderator->id]);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_suspending_a_moderator_revokes_access_and_blocks_login(): void
    {
        $moderator = User::factory()->moderator()->create(['password' => 'secret-password']);
        $moderator->createToken('nuxt');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/v1/admin/moderators/{$moderator->id}", ['suspended' => true])
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true);
        $this->assertSame(0, $moderator->tokens()->count());

        $this->postJson('/api/v1/auth/login', ['email' => $moderator->email, 'password' => 'secret-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_password_replaces_the_old_one(): void
    {
        $moderator = User::factory()->moderator()->create(['password' => 'old-password']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $password = $this->postJson("/api/v1/admin/moderators/{$moderator->id}/reset-password")
            ->assertOk()
            ->json('meta.password');

        $this->assertFalse(Hash::check('old-password', $moderator->fresh()->password));
        $this->assertTrue(Hash::check($password, $moderator->fresh()->password));
    }

    public function test_moderator_endpoints_return_404_for_non_moderator_accounts(): void
    {
        $merchant = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/v1/admin/moderators/{$merchant->id}")->assertNotFound();
        $this->assertModelExists($merchant);
    }

    public function test_super_admin_deletes_a_moderator(): void
    {
        $moderator = User::factory()->moderator()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/v1/admin/moderators/{$moderator->id}")->assertNoContent();
        $this->assertModelMissing($moderator);
    }
}

<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\AI\LanguageModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssistantControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shop_setup_detects_template_and_name_locally(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ai/shop-setup', ['prompt' => 'Je vends des savons et du beurre de karité, ma boutique s\'appelle Douceur Karité'])
            ->assertOk()
            ->assertJsonPath('data.template', 'beauty')
            ->assertJsonPath('data.name', 'Douceur Karité');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function businessDescriptions(): array
    {
        return [
            'restaurant' => ['Je tiens un maquis et je livre du garba et des grillades', 'restaurant'],
            'phones in Plateau district' => ['Je vends des téléphones et accessoires au Plateau', 'tech'],
            'single product' => ['Je fais du dropshipping avec un produit phare', 'single'],
            'organic farm' => ['Coopérative de producteurs de légumes bio', 'organic'],
            'no keyword' => ['Je vends un peu de tout', 'minimal'],
        ];
    }

    #[DataProvider('businessDescriptions')]
    public function test_shop_setup_recommends_the_template_matching_the_business(string $prompt, string $expectedTemplate): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ai/shop-setup', ['prompt' => $prompt])
            ->assertOk()
            ->assertJsonPath('data.template', $expectedTemplate);
    }

    public function test_shop_setup_uses_language_model_suggestion_when_available(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(LanguageModel::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturnTrue();
            $mock->shouldReceive('structured')->once()->andReturn([
                'name' => 'Tech Plateau', 'tagline' => 'Le high-tech à Abidjan', 'sector' => 'electronique',
                'template' => 'tech', 'description' => 'Boutique de gadgets.', 'categories' => ['Téléphones'],
            ]);
        });

        $this->postJson('/api/v1/ai/shop-setup', ['prompt' => 'Accessoires de téléphone au Plateau'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tech Plateau')
            ->assertJsonPath('data.categories', ['Téléphones']);
    }

    public function test_product_description_returns_text_and_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/ai/product-description', [
            'name' => 'Sac en raphia',
            'features' => 'tressé main, doublure coton',
            'tone' => 'luxe',
        ])->assertOk();

        $this->assertStringContainsString('Sac en raphia incarne', $response->json('data.description'));
        $this->assertContains('raphia', $response->json('data.tags'));
    }

    public function test_ai_endpoints_return_401_without_token(): void
    {
        $this->postJson('/api/v1/ai/shop-setup', ['prompt' => 'Je vends des savons'])->assertUnauthorized();
    }
}

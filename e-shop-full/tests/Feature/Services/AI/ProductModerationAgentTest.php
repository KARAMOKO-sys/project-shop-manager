<?php

namespace Tests\Feature\Services\AI;

use App\Enums\ModerationStatus;
use App\Models\Product;
use App\Services\AI\LanguageModel;
use App\Services\AI\ProductModerationAgent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductModerationAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_language_model_can_flag_a_listing_the_local_rules_accept(): void
    {
        $this->mock(LanguageModel::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturnTrue();
            $mock->shouldReceive('name')->andReturn('claude');
            $mock->shouldReceive('structured')->once()->andReturn([
                'risk_score' => 85,
                'reasons' => ['Médicament vendu sans autorisation.'],
            ]);
        });
        $product = Product::factory()->create(['description' => 'Comprimés puissants pour maigrir vite, très efficaces.']);

        app(ProductModerationAgent::class)->moderate($product);

        $product->refresh();
        $this->assertSame(ModerationStatus::Rejected, $product->moderation_status);
        $this->assertSame('claude', $product->moderation_engine);
        $this->assertSame(['Médicament vendu sans autorisation.'], $product->moderation_reasons);
    }

    public function test_local_verdict_wins_when_language_model_is_more_lenient(): void
    {
        $this->mock(LanguageModel::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturnTrue();
            $mock->shouldReceive('name')->andReturn('claude');
            $mock->shouldReceive('structured')->once()->andReturn(['risk_score' => 0, 'reasons' => []]);
        });
        $product = Product::factory()->create(['name' => 'Kalachnikov', 'description' => 'Arme a feu avec munition incluse.']);

        $result = app(ProductModerationAgent::class)->review($product);

        $this->assertSame(ModerationStatus::Rejected, $result->status());
        $this->assertSame('local', $result->engine);
    }

    public function test_local_rules_decide_when_language_model_call_fails(): void
    {
        $this->mock(LanguageModel::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturnTrue();
            $mock->shouldReceive('structured')->once()->andReturnNull();
        });
        $product = Product::factory()->create(['description' => 'Panier tressé à la main par des artisanes de Bolgatanga.']);

        $result = app(ProductModerationAgent::class)->review($product);

        $this->assertSame(ModerationStatus::Approved, $result->status());
    }
}

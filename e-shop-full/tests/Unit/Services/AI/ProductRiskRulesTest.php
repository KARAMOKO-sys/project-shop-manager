<?php

namespace Tests\Unit\Services\AI;

use App\Models\Product;
use App\Services\AI\ProductRiskRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductRiskRulesTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function evaluate(array $attributes): int
    {
        $product = new Product([
            'name' => 'Produit',
            'description' => 'Une description suffisamment détaillée pour l\'acheteur.',
            'price' => 5000,
            ...$attributes,
        ]);

        return (new ProductRiskRules)->evaluate($product)->riskScore;
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function legitimateListings(): array
    {
        return [
            'wax fabric' => [['name' => 'Pagne wax 6 yards', 'description' => 'Pagne wax hollandais en coton, motifs exclusifs.', 'price' => 12000]],
            'shea butter' => [['name' => 'Beurre de karité', 'description' => 'Beurre de karité brut extrait à froid au Burkina Faso.', 'price' => 2500]],
            'genuine priced brand' => [['name' => 'iPhone 15 reconditionné', 'description' => 'iPhone 15 128 Go reconditionné, garantie 6 mois.', 'price' => 450000]],
            'word containing a pattern' => [['name' => 'Pistolet à colle chaude', 'description' => 'Outil de bricolage pour vos créations, livré avec 10 bâtons.', 'price' => 6000]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('legitimateListings')]
    public function test_legitimate_listings_score_below_review_threshold(array $attributes): void
    {
        $this->assertLessThan(30, $this->evaluate($attributes));
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function riskyListings(): array
    {
        return [
            'weapon' => [['name' => 'Fusil de chasse', 'description' => 'Fusil calibre 12 avec munitions.'], 70],
            'drugs' => [['name' => 'Tramadol 100 mg', 'description' => 'Boîte de tramadol sans ordonnance.'], 70],
            'fake document' => [['name' => 'Faux passeport', 'description' => 'Faux passeport livré en 48 h.'], 70],
            'cheap luxury brand' => [['name' => 'Sac Louis Vuitton', 'description' => 'Sac Louis Vuitton neuf avec étiquettes.', 'price' => 15000], 30],
            'fake discount' => [['name' => 'Crème visage', 'description' => 'Crème hydratante pour tous types de peau.', 'price' => 2000, 'compare_at_price' => 50000], 30],
            'phone number' => [['name' => 'Chaussures', 'description' => 'Belles chaussures, appelez le 07 08 09 10 11 pour commander.'], 25],
            'scam' => [['name' => 'Formation', 'description' => 'Doublez votre argent en une semaine, gains garantis.'], 70],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('riskyListings')]
    public function test_risky_listings_reach_expected_minimum_score(array $attributes, int $minimumScore): void
    {
        $this->assertGreaterThanOrEqual($minimumScore, $this->evaluate($attributes));
    }

    public function test_score_is_capped_at_100(): void
    {
        $this->assertSame(100, $this->evaluate([
            'name' => 'Pistolet et cannabis',
            'description' => 'Fusil, cannabis, faux passeport, ivoire, réplique, western union.',
        ]));
    }
}

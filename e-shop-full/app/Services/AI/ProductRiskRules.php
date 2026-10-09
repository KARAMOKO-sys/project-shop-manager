<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Deterministic fraud and policy checks that run on every product, with or without an LLM.
 */
class ProductRiskRules
{
    /**
     * Prohibited or high-risk listing patterns, grouped by policy.
     *
     * @var array<string, array{weight: int, reason: string, patterns: list<string>}>
     */
    private const POLICIES = [
        'weapons' => [
            'weight' => 80,
            'reason' => 'Vente d\'armes ou de munitions interdite.',
            'patterns' => ['arme a feu', 'pistolet automatique', 'pistolet 9mm', 'fusil', 'munition', 'revolver', 'kalachnikov', 'firearm', 'handgun', 'ammo'],
        ],
        'drugs' => [
            'weight' => 80,
            'reason' => 'Vente de stupéfiants ou de médicaments réglementés interdite.',
            'patterns' => ['cannabis', 'cocaine', 'heroine', 'tramadol', 'codeine', 'drogue', 'weed', 'ecstasy', 'sans ordonnance'],
        ],
        'counterfeit' => [
            'weight' => 60,
            'reason' => 'Le produit semble être une contrefaçon ou une réplique.',
            'patterns' => ['replique', 'replica', 'contrefacon', 'copie conforme', 'copie originale', 'qualite 1:1', 'master copy', 'faux sac', 'first copy'],
        ],
        'documents' => [
            'weight' => 90,
            'reason' => 'Vente de faux documents officiels interdite.',
            'patterns' => ['faux passeport', 'faux diplome', 'faux permis', 'fake passport', 'fake id', 'carte d\'identite a vendre'],
        ],
        'wildlife' => [
            'weight' => 80,
            'reason' => 'Produits issus d\'espèces protégées interdits.',
            'patterns' => ['ivoire', 'ivory', 'peau de leopard', 'corne de rhinoceros', 'ecaille de pangolin', 'pangolin'],
        ],
        'health_claims' => [
            'weight' => 45,
            'reason' => 'Allégations de santé trompeuses (guérison miracle, résultats garantis).',
            'patterns' => ['guerit', 'guerison miracle', 'remede miracle', 'soigne le diabete', 'soigne le vih', 'perte de poids garantie', 'cure cancer'],
        ],
        'scam' => [
            'weight' => 70,
            'reason' => 'Promesse de gains financiers typique d\'une arnaque.',
            'patterns' => ['argent facile', 'doublez votre argent', 'gains garantis', 'investissement garanti', 'devenez riche', 'multiplication d\'argent'],
        ],
        'off_platform_payment' => [
            'weight' => 50,
            'reason' => 'Incitation à payer hors de la plateforme (risque d\'escroquerie).',
            'patterns' => ['western union', 'moneygram', 'payez avant', 'envoyez l\'argent', 'paiement par transfert uniquement', 'contactez-moi sur whatsapp'],
        ],
        'adult' => [
            'weight' => 70,
            'reason' => 'Contenu pour adultes non autorisé.',
            'patterns' => ['pornographique', 'xxx', 'escort'],
        ],
    ];

    /**
     * Brands frequently counterfeited, with the lowest plausible genuine price in XOF.
     *
     * @var array<string, int>
     */
    private const LUXURY_BRANDS = [
        'rolex' => 2_000_000,
        'louis vuitton' => 300_000,
        'gucci' => 200_000,
        'chanel' => 200_000,
        'hermes' => 300_000,
        'dior' => 150_000,
        'prada' => 200_000,
        'iphone' => 150_000,
        'playstation 5' => 200_000,
        'macbook' => 400_000,
    ];

    public function evaluate(Product $product): ModerationResult
    {
        $text = $this->normalize(implode(' ', [
            $product->name,
            $product->description,
            implode(' ', $product->tags ?? []),
        ]));

        $score = 0;
        $reasons = [];

        foreach (self::POLICIES as $policy) {
            if ($this->containsAny($text, $policy['patterns'])) {
                $score += $policy['weight'];
                $reasons[] = $policy['reason'];
            }
        }

        if (preg_match('/(\+?\d[\d\s.-]{7,}\d)/', (string) $product->description)) {
            $score += 25;
            $reasons[] = 'La description contient un numéro de téléphone (contact hors plateforme).';
        }

        $price = (float) $product->price;
        $compareAtPrice = (float) $product->compare_at_price;

        if ($price <= 0) {
            $score += 40;
            $reasons[] = 'Le prix est nul ou négatif.';
        }

        if ($price > 0 && $compareAtPrice > $price * 5) {
            $score += 30;
            $reasons[] = 'Réduction affichée irréaliste (prix barré plus de 5 fois supérieur).';
        }

        foreach (self::LUXURY_BRANDS as $brand => $minimumPrice) {
            if (str_contains($text, $brand) && $price > 0 && $price < $minimumPrice * 0.4) {
                $score += 45;
                $reasons[] = "Prix anormalement bas pour un article de marque « {$brand} » : risque de contrefaçon.";
                break;
            }
        }

        if (Str::length(trim((string) $product->description)) < 20) {
            $score += 5;
            $reasons[] = 'Description trop courte pour informer correctement l\'acheteur.';
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $product->name);
        if (Str::length($letters) > 8 && $letters === Str::upper($letters)) {
            $score += 5;
            $reasons[] = 'Titre entièrement en majuscules (pratique de spam).';
        }

        return new ModerationResult(min($score, 100), $reasons, 'local');
    }

    private function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * @param  list<string>  $patterns
     */
    private function containsAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/u', $text)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

/**
 * Writes persuasive, SEO-friendly product descriptions and tags.
 */
class ProductCopywriter
{
    /**
     * @var array<string, string>
     */
    private const TONE_INTROS = [
        'professionnel' => 'Découvrez :name, un produit fiable pensé pour répondre à vos besoins au quotidien.',
        'decontracte' => 'Craquez pour :name, le petit plus qui va vite devenir indispensable !',
        'luxe' => ':name incarne l\'élégance et le raffinement, pour celles et ceux qui exigent le meilleur.',
    ];

    public function __construct(private LanguageModel $languageModel) {}

    /**
     * @return array{description: string, tags: list<string>}
     */
    public function describe(string $name, ?string $features, string $tone): array
    {
        return $this->askLanguageModel($name, $features, $tone) ?? $this->describeLocally($name, $features, $tone);
    }

    /**
     * @return array{description: string, tags: list<string>}|null
     */
    private function askLanguageModel(string $name, ?string $features, string $tone): ?array
    {
        if (! $this->languageModel->isAvailable()) {
            return null;
        }

        $result = $this->languageModel->structured(
            system: 'You are an e-commerce copywriter for small merchants in francophone Africa and Europe. '
                .'Write in French a persuasive, honest, SEO-friendly product description of 80 to 150 words that highlights benefits, '
                .'then 3 to 8 lowercase search tags. Never invent certifications, health claims or guarantees that the merchant did not provide.',
            prompt: "Produit : {$name}\nCaractéristiques fournies : ".($features ?: 'aucune')."\nTon souhaité : {$tone}",
            schema: [
                'type' => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['description', 'tags'],
                'additionalProperties' => false,
            ],
        );

        if ($result === null) {
            return null;
        }

        return [
            'description' => $result['description'],
            'tags' => array_values(array_slice(array_map(Str::lower(...), $result['tags']), 0, 8)),
        ];
    }

    /**
     * @return array{description: string, tags: list<string>}
     */
    private function describeLocally(string $name, ?string $features, string $tone): array
    {
        $featureList = collect(preg_split('/[,;\n]+/', (string) $features))
            ->map(fn (string $feature) => trim($feature))
            ->filter();

        $description = Str::replace(':name', $name, self::TONE_INTROS[$tone] ?? self::TONE_INTROS['professionnel']);

        if ($featureList->isNotEmpty()) {
            $description .= "\n\nPoints forts :\n".$featureList->map(fn (string $feature) => '• '.Str::ucfirst($feature))->implode("\n");
        }

        $description .= "\n\nCommandez dès maintenant et profitez d'une livraison rapide et d'un paiement sécurisé, y compris par Mobile Money.";

        $tags = collect(preg_split('/\s+/', Str::lower(Str::ascii($name.' '.$featureList->implode(' ')))))
            ->map(fn (string $word) => trim($word, '.,;:!?()"\''))
            ->filter(fn (string $word) => Str::length($word) > 3)
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return ['description' => $description, 'tags' => $tags];
    }
}

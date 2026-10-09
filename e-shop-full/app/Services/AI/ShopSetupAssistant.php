<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

/**
 * Turns a one-sentence business description into a ready-to-use shop configuration.
 */
class ShopSetupAssistant
{
    /**
     * Keywords used by the local engine to guess the template from the description.
     *
     * @var array<string, list<string>>
     */
    private const TEMPLATE_KEYWORDS = [
        'fashion' => ['vetement', 'mode', 'robe', 'chemise', 'pagne', 'wax', 'chaussure', 'boubou', 'couture', 'pret-a-porter', 'styliste'],
        'food' => ['epicerie', 'epice', 'miel', 'confiture', 'boisson', 'jus', 'cafe', 'cacao', 'condiment'],
        'craft' => ['artisan', 'artisanal', 'fait main', 'sculpture', 'poterie', 'vannerie', 'perle', 'tissage', 'calebasse'],
        'tech' => ['telephone', 'smartphone', 'ordinateur', 'electronique', 'gadget', 'informatique', 'ecouteur', 'console', 'tablette'],
        'beauty' => ['beaute', 'cosmetique', 'karite', 'savon', 'soin', 'parfum', 'cheveux', 'perruque', 'maquillage'],
        'restaurant' => ['restaurant', 'traiteur', 'plat', 'cuisine', 'maquis', 'grillade', 'fast-food', 'pizza', 'patisserie', 'livraison de repas', 'attieke', 'garba'],
        'single' => ['un seul produit', 'produit unique', 'dropshipping', 'produit phare', 'lancement'],
        'luxury' => ['luxe', 'bijou', 'bijouterie', 'joaillerie', 'montre', 'diamant', 'haut de gamme'],
        'market' => ['supermarche', 'superette', 'grossiste', 'boutique de quartier', 'alimentation generale', 'rayons', 'quincaillerie'],
        'kids' => ['enfant', 'bebe', 'jouet', 'puericulture', 'naissance', 'ecole'],
        'home' => ['decoration', 'meuble', 'mobilier', 'maison', 'luminaire', 'linge de maison', 'coussin'],
        'sport' => ['sport', 'fitness', 'football', 'musculation', 'running', 'maillot', 'gym'],
        'organic' => ['bio', 'ferme', 'fermier', 'producteur', 'legume', 'fruit', 'cooperative', 'maraicher', 'agricole'],
    ];

    public function __construct(private LanguageModel $languageModel) {}

    /**
     * @return array{name: string, tagline: string, sector: string, template: string, description: string, categories: list<string>}
     */
    public function suggest(string $businessDescription): array
    {
        return $this->askLanguageModel($businessDescription) ?? $this->suggestLocally($businessDescription);
    }

    /**
     * @return array{name: string, tagline: string, sector: string, template: string, description: string, categories: list<string>}|null
     */
    private function askLanguageModel(string $businessDescription): ?array
    {
        if (! $this->languageModel->isAvailable()) {
            return null;
        }

        $templates = collect(config('shopai.templates'))
            ->map(fn (array $template, string $key) => "- {$key}: {$template['name']} ({$template['description']})")
            ->implode("\n");

        $suggestion = $this->languageModel->structured(
            system: <<<PROMPT
                You help small merchants in francophone Africa and Europe launch an online shop in minutes.
                From the merchant's description, propose a shop configuration written in French: a short memorable shop name,
                a catchy tagline (under 80 characters), a sector, a warm 2-3 sentence "about" description, and 3 to 6 product categories.
                Pick the best template key among:
                {$templates}
                PROMPT,
            prompt: $businessDescription,
            schema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'tagline' => ['type' => 'string'],
                    'sector' => ['type' => 'string'],
                    'template' => ['type' => 'string', 'enum' => array_keys(config('shopai.templates'))],
                    'description' => ['type' => 'string'],
                    'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['name', 'tagline', 'sector', 'template', 'description', 'categories'],
                'additionalProperties' => false,
            ],
        );

        if ($suggestion === null || ! array_key_exists($suggestion['template'], config('shopai.templates'))) {
            return null;
        }

        $suggestion['categories'] = array_values(array_slice($suggestion['categories'], 0, 6));

        return $suggestion;
    }

    /**
     * @return array{name: string, tagline: string, sector: string, template: string, description: string, categories: list<string>}
     */
    private function suggestLocally(string $businessDescription): array
    {
        $text = Str::lower(Str::ascii($businessDescription));
        $templateKey = 'minimal';
        $bestScore = 0;

        foreach (self::TEMPLATE_KEYWORDS as $key => $keywords) {
            $score = collect($keywords)
                ->filter(fn (string $keyword) => preg_match('/\b'.preg_quote(trim($keyword), '/').'(s|x)?\b/', $text))
                ->count();

            if ($score > $bestScore) {
                [$templateKey, $bestScore] = [$key, $score];
            }
        }

        $template = config("shopai.templates.{$templateKey}");

        return [
            'name' => $this->guessShopName($businessDescription, $template['name']),
            'tagline' => 'Des produits choisis avec soin, livrés près de chez vous.',
            'sector' => $template['sector'],
            'template' => $templateKey,
            'description' => 'Bienvenue dans notre boutique ! '.Str::ucfirst(Str::finish(trim($businessDescription), '.'))
                .' Commandez en quelques clics et payez comme vous le souhaitez, y compris par Mobile Money.',
            'categories' => $template['default_categories'],
        ];
    }

    private function guessShopName(string $businessDescription, string $fallback): string
    {
        if (preg_match('/(?:appel(?:le|é)e?|nomm(?:e|é)e?|s\'appelle)\s+[«"\']?([\p{L}\d][\p{L}\d\s&\'-]{1,40}?)[»"\']?(?:[.,!]|$)/iu', $businessDescription, $matches)) {
            return Str::title(trim($matches[1]));
        }

        return "Boutique {$fallback}";
    }
}

<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide settings an administrator can change without touching the code.
 *
 * Every setting has a default (from config/shopai.php when one exists); only the values an
 * administrator changed are stored in the `platform_settings` table.
 */
class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings';

    /**
     * Groups shown in the administration screen, in display order.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'platform' => 'Identité de la plateforme',
        'features' => 'Fonctionnalités',
        'payments' => 'Paiements',
        'limits' => 'Limites',
        'ai' => 'Intelligence artificielle',
        'moderation' => 'Modération des produits',
    ];

    /**
     * Stored overrides, memoized for the lifetime of the instance.
     *
     * @var array<string, mixed>|null
     */
    private ?array $overrides = null;

    /**
     * Every setting the platform understands.
     *
     * @return array<string, array{group: string, type: 'string'|'text'|'email'|'boolean'|'integer'|'select', label: string, help?: string, default: mixed, public: bool, options?: array<string, string>, min?: int, max?: int}>
     */
    public function definitions(): array
    {
        return [
            'platform.name' => ['group' => 'platform', 'type' => 'string', 'label' => 'Nom de la plateforme', 'default' => 'ShopAI', 'public' => true, 'max' => 60],
            'platform.tagline' => ['group' => 'platform', 'type' => 'string', 'label' => 'Slogan', 'default' => 'La plateforme e-commerce propulsée par l’IA pour les commerçants d’Afrique francophone.', 'public' => true, 'max' => 200],
            'platform.support_email' => ['group' => 'platform', 'type' => 'email', 'label' => 'E-mail du support', 'default' => null, 'public' => true, 'max' => 255],
            'platform.support_whatsapp' => ['group' => 'platform', 'type' => 'string', 'label' => 'WhatsApp du support', 'help' => 'Numéro au format international, ex. +225 07 00 00 00 00.', 'default' => null, 'public' => true, 'max' => 30],
            'platform.announcement' => ['group' => 'platform', 'type' => 'text', 'label' => 'Bandeau d’annonce', 'help' => 'Affiché en haut de toutes les pages publiques. Laissez vide pour le masquer.', 'default' => null, 'public' => true, 'max' => 300],

            'features.registration' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Inscriptions ouvertes', 'help' => 'Permet aux nouveaux marchands de créer un compte.', 'default' => true, 'public' => true],
            'features.shop_creation' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Création de boutiques', 'help' => 'Permet aux marchands d’ouvrir de nouvelles boutiques.', 'default' => true, 'public' => true],
            'features.ai_assistant' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Assistant IA du marchand', 'help' => 'Configuration de boutique et rédaction de fiches produits par l’IA.', 'default' => true, 'public' => true],
            'features.platform_chat' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Coach IA de la plateforme', 'help' => 'Chat d’aide affiché sur le site et le tableau de bord.', 'default' => true, 'public' => true],
            'features.shop_chat' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Service client IA des boutiques', 'help' => 'Chat répondant aux clients sur chaque boutique.', 'default' => true, 'public' => true],
            'features.recommendations' => ['group' => 'features', 'type' => 'boolean', 'label' => 'Recommandations de produits', 'help' => 'Suggestions « Vous aimerez aussi » sur les fiches produits.', 'default' => true, 'public' => true],

            'payments.cash_on_delivery' => ['group' => 'payments', 'type' => 'boolean', 'label' => 'Paiement à la livraison', 'help' => 'Désactivé ici, il l’est pour toutes les boutiques.', 'default' => true, 'public' => true],
            'payments.online' => ['group' => 'payments', 'type' => 'boolean', 'label' => 'Paiement en ligne (Mobile Money, carte)', 'help' => 'Désactivé ici, il l’est pour toutes les boutiques.', 'default' => true, 'public' => true],

            'limits.max_shops_per_user' => ['group' => 'limits', 'type' => 'integer', 'label' => 'Boutiques maximum par marchand', 'help' => '0 = illimité.', 'default' => 0, 'public' => false, 'min' => 0, 'max' => 1000],
            'limits.max_products_per_shop' => ['group' => 'limits', 'type' => 'integer', 'label' => 'Produits maximum par boutique', 'help' => '0 = illimité.', 'default' => 0, 'public' => false, 'min' => 0, 'max' => 100000],

            'ai.driver' => ['group' => 'ai', 'type' => 'select', 'label' => 'Moteur IA', 'help' => 'Claude n’est utilisé que si la clé ANTHROPIC_API_KEY est définie sur le serveur.', 'default' => config('shopai.ai.driver'), 'public' => false, 'options' => ['auto' => 'Automatique (Claude si disponible)', 'local' => 'Moteur local uniquement']],
            'ai.model' => ['group' => 'ai', 'type' => 'string', 'label' => 'Modèle Claude', 'default' => config('shopai.ai.model'), 'public' => false, 'max' => 100],
            'ai.requests_per_minute' => ['group' => 'ai', 'type' => 'integer', 'label' => 'Requêtes IA par minute et par utilisateur', 'default' => 20, 'public' => false, 'min' => 1, 'max' => 600],

            'moderation.review_threshold' => ['group' => 'moderation', 'type' => 'integer', 'label' => 'Seuil d’examen humain', 'help' => 'À partir de ce score de risque, le produit attend la décision d’un administrateur.', 'default' => config('shopai.moderation.review_threshold'), 'public' => false, 'min' => 1, 'max' => 100],
            'moderation.reject_threshold' => ['group' => 'moderation', 'type' => 'integer', 'label' => 'Seuil de refus automatique', 'help' => 'À partir de ce score de risque, le produit est refusé automatiquement.', 'default' => config('shopai.moderation.reject_threshold'), 'public' => false, 'min' => 1, 'max' => 100],
        ];
    }

    /**
     * Current value of every setting, keyed by its dotted name.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $overrides = $this->overrides();

        return collect($this->definitions())
            ->map(fn (array $definition, string $key): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $definition['default'])
            ->all();
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function featureEnabled(string $feature): bool
    {
        return (bool) $this->get("features.{$feature}");
    }

    /**
     * Settings safe to expose to visitors, nested by group.
     *
     * @return array<string, mixed>
     */
    public function publicValues(): array
    {
        $publicKeys = array_keys(array_filter($this->definitions(), fn (array $definition): bool => $definition['public']));

        return Arr::undot(Arr::only($this->all(), $publicKeys));
    }

    /**
     * Validation rules for an update payload nested by group (e.g. `features.registration`).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return collect($this->definitions())->map(fn (array $definition): array => match ($definition['type']) {
            'boolean' => ['sometimes', 'boolean'],
            'integer' => ['sometimes', 'integer', "min:{$definition['min']}", "max:{$definition['max']}"],
            'select' => ['sometimes', 'string', 'in:'.implode(',', array_keys($definition['options']))],
            'email' => ['sometimes', 'nullable', 'email', "max:{$definition['max']}"],
            'string' => ['sometimes', ...($definition['default'] === null ? ['nullable'] : ['required']), 'string', "max:{$definition['max']}"],
            'text' => ['sometimes', 'nullable', 'string', "max:{$definition['max']}"],
        })->all();
    }

    /**
     * Persist the given values (nested by group) and forget the cached copy.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $definitions = $this->definitions();
        $changes = Arr::only(Arr::dot($values), array_keys($definitions));

        DB::transaction(function () use ($changes, $definitions): void {
            foreach ($changes as $key => $value) {
                PlatformSetting::updateOrCreate(['key' => $key], ['value' => $this->normalize($value, $definitions[$key]['type'])]);
            }
        });

        Cache::forget(self::CACHE_KEY);
        $this->overrides = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        return $this->overrides ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => PlatformSetting::all()->mapWithKeys(fn (PlatformSetting $setting): array => [$setting->key => $setting->value])->all(),
        );
    }

    private function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => blank($value) ? null : trim((string) $value),
        };
    }
}

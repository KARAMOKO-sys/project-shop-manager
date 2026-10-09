<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopService
{
    /**
     * Create a shop for the merchant, ready to sell, with its categories.
     *
     * @param  array{name: string, template: string, tagline?: ?string, description?: ?string, sector?: ?string, currency?: ?string, theme?: ?array<string, string>, categories?: ?list<string>}  $payload
     */
    public function create(User $owner, array $payload): Shop
    {
        return DB::transaction(function () use ($owner, $payload) {
            $template = config("shopai.templates.{$payload['template']}");

            $shop = $owner->shops()->create([
                'name' => $payload['name'],
                'slug' => $this->uniqueSlug($payload['name']),
                'tagline' => $payload['tagline'] ?? null,
                'description' => $payload['description'] ?? null,
                'sector' => $payload['sector'] ?? $template['sector'],
                'template' => $payload['template'],
                'theme' => $payload['theme'] ?? null,
                'currency' => $payload['currency'] ?? 'XOF',
                'language' => 'fr',
                'status' => 'active',
            ]);

            $categories = collect($payload['categories'] ?? $template['default_categories'])
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->unique(fn (string $name) => Str::slug($name));

            foreach ($categories as $name) {
                $shop->categories()->create(['name' => $name, 'slug' => Str::slug($name)]);
            }

            return $shop;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'boutique';
        $slug = $base;
        $suffix = 2;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

<?php

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Jobs\ModerateProduct;
use App\Models\Product;
use App\Models\Shop;
use App\Services\AI\LanguageModel;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private LanguageModel $languageModel) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Shop $shop, array $payload): Product
    {
        $payload['slug'] = $this->uniqueSlug($shop, $payload['name']);
        $payload['stock_status'] = ($payload['stock_quantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock';
        $payload['moderation_status'] = ModerationStatus::Pending;

        $product = $shop->products()->create($payload);

        $this->requestModeration($product);

        return $product->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Product $product, array $payload): Product
    {
        $product->fill($payload);

        if ($product->isDirty('name')) {
            $product->slug = $this->uniqueSlug($product->shop, $product->name, $product->id);
        }

        if ($product->isDirty('stock_quantity')) {
            $product->stock_status = $product->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        }

        $needsReview = $product->isDirty(Product::MODERATED_FIELDS);

        if ($needsReview) {
            $product->moderation_status = ModerationStatus::Pending;
        }

        $product->save();

        if ($needsReview) {
            $this->requestModeration($product);
        }

        return $product->refresh();
    }

    /**
     * Send the product to the moderation agent. The local engine is instant, so it
     * runs inline; reviews that call an external model are queued.
     */
    public function requestModeration(Product $product): void
    {
        if ($this->languageModel->isAvailable()) {
            ModerateProduct::dispatch($product);

            return;
        }

        ModerateProduct::dispatchSync($product);
    }

    private function uniqueSlug(Shop $shop, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $suffix = 2;

        while ($shop->products()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

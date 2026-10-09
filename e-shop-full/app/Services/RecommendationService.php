<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Les clients ont aussi acheté": products bought in the same orders, completed
 * with products of the same category, then the shop's featured products.
 */
class RecommendationService
{
    /**
     * @return Collection<int, Product>
     */
    public function forProduct(Product $product, int $limit = 4): Collection
    {
        $boughtTogetherIds = OrderItem::query()
            ->whereIn('order_id', OrderItem::select('order_id')->where('product_id', $product->id))
            ->where('product_id', '!=', $product->id)
            ->groupBy('product_id')
            ->orderByRaw('count(*) desc')
            ->limit($limit * 3)
            ->pluck('product_id');

        $candidates = $product->shop->products()
            ->publiclyVisible()
            ->whereKeyNot($product->id)
            ->where('stock_quantity', '>', 0);

        $recommendations = (clone $candidates)
            ->whereKey($boughtTogetherIds)
            ->with('shop', 'category')
            ->get()
            ->sortBy(fn (Product $candidate) => $boughtTogetherIds->search($candidate->id))
            ->take($limit)
            ->values();

        if ($recommendations->count() < $limit) {
            $fillers = (clone $candidates)
                ->whereKeyNot($recommendations->modelKeys())
                ->with('shop', 'category')
                ->orderByRaw('category_id = ? desc', [$product->category_id ?? 0])
                ->orderByDesc('is_featured')
                ->latest()
                ->limit($limit - $recommendations->count())
                ->get();

            $recommendations = $recommendations->concat($fillers);
        }

        return new Collection($recommendations->all());
    }
}

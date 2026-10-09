<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'shop' => $this->whenLoaded('shop', fn () => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'slug' => $this->shop->slug,
            ]),
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'currency' => $this->whenLoaded('shop', fn () => $this->shop->currency, 'XOF'),
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'image_url' => $this->image_url,
            'tags' => $this->tags ?? [],
            'moderation' => [
                'status' => $this->moderation_status,
                'risk_score' => $this->moderation_risk_score,
                'reasons' => $this->moderation_reasons ?? [],
                'note' => $this->moderation_note,
                'checked_at' => $this->moderated_at,
                'engine' => $this->moderation_engine,
            ],
            'is_public' => $this->isPubliclyVisible(),
            'created_at' => $this->created_at,
        ];
    }
}

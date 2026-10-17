<?php

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shop
 */
class ShopResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'sector' => $this->sector,
            'template' => $this->template,
            'theme' => $this->resolvedTheme(),
            'currency' => $this->currency,
            'language' => $this->language,
            'logo_url' => $this->logo_url,
            'status' => $this->status,
            'cash_on_delivery_enabled' => $this->cash_on_delivery_enabled,
            'flutterwave_enabled' => $this->flutterwave_enabled,
            'flutterwave_configured' => filled($this->flutterwave_secret_key) && filled($this->flutterwave_webhook_secret_hash),
            'payment_methods' => $this->availablePaymentMethods(),
            'products_count' => $this->whenCounted('products'),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'url' => rtrim(config('shopai.frontend_url'), '/')."/boutique/{$this->slug}",
        ];
    }
}

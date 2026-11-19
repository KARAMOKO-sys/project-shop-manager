<?php

namespace App\Http\Resources;

use App\Models\Shop;
use App\Services\Erp\ModuleRegistry;
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
            'tax_rate' => (float) $this->tax_rate,
            'logo_url' => $this->logo_url,
            'status' => $this->status,
            'cash_on_delivery_enabled' => $this->cash_on_delivery_enabled,
            'flutterwave_enabled' => $this->flutterwave_enabled,
            'flutterwave_configured' => filled($this->flutterwave_secret_key) && filled($this->flutterwave_webhook_secret_hash),
            'payment_methods' => $this->availablePaymentMethods(),
            'delivery' => [
                'enabled' => $this->delivery_enabled,
                'fee' => (float) $this->delivery_fee,
                'free_threshold' => $this->free_delivery_threshold !== null ? (float) $this->free_delivery_threshold : null,
                'zone' => $this->delivery_zone,
                'delay' => $this->delivery_delay,
                'pickup_enabled' => $this->pickup_enabled,
                'pickup_address' => $this->pickup_address,
                'methods' => $this->availableDeliveryMethods(),
            ],
            'modules' => app(ModuleRegistry::class)->installed($this->resource),
            'products_count' => $this->whenCounted('products'),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'url' => rtrim(config('shopai.frontend_url'), '/')."/boutique/{$this->slug}",
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'stock_status',
        'status',
        'is_featured',
        'image_url',
        'tags',
        'moderation_status',
        'moderation_risk_score',
        'moderation_reasons',
        'moderation_engine',
        'moderation_note',
        'moderated_at',
    ];

    /**
     * Fields whose change requires a new review by the moderation agent.
     *
     * @var list<string>
     */
    public const MODERATED_FIELDS = ['name', 'description', 'price', 'compare_at_price', 'image_url', 'tags', 'category_id'];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_featured' => 'boolean',
        'tags' => 'array',
        'status' => ProductStatus::class,
        'moderation_status' => ModerationStatus::class,
        'moderation_reasons' => 'array',
        'moderated_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Products that passed moderation, are published, and belong to an active shop.
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->where('moderation_status', ModerationStatus::Approved)
            ->whereHas('shop', fn (Builder $shop) => $shop->where('status', 'active'));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === ProductStatus::Published
            && $this->moderation_status === ModerationStatus::Approved;
    }
}

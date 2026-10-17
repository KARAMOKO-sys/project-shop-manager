<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Services\PlatformSettings;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'tagline',
        'slug',
        'description',
        'sector',
        'template',
        'theme',
        'logo_url',
        'domain',
        'currency',
        'language',
        'tax_rate',
        'status',
        'flutterwave_secret_key',
        'flutterwave_webhook_secret_hash',
        'flutterwave_enabled',
        'cash_on_delivery_enabled',
    ];

    protected $casts = [
        'theme' => 'array',
        'tax_rate' => 'decimal:2',
        'flutterwave_secret_key' => 'encrypted',
        'flutterwave_webhook_secret_hash' => 'encrypted',
        'flutterwave_enabled' => 'boolean',
        'cash_on_delivery_enabled' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * The template's default theme merged with the merchant's overrides.
     *
     * @return array<string, string>
     */
    public function resolvedTheme(): array
    {
        $templateTheme = config("shopai.templates.{$this->template}.theme")
            ?? config('shopai.templates.minimal.theme');

        return array_merge($templateTheme, $this->theme ?? []);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return list<string>
     */
    public function availablePaymentMethods(): array
    {
        $methods = [];

        if ($this->acceptsPaymentMethod(PaymentMethod::CashOnDelivery)) {
            $methods[] = PaymentMethod::CashOnDelivery->value;
        }

        if ($this->acceptsPaymentMethod(PaymentMethod::Flutterwave)) {
            $methods[] = PaymentMethod::Flutterwave->value;
        }

        return $methods;
    }

    public function acceptsPaymentMethod(PaymentMethod $method): bool
    {
        $settings = app(PlatformSettings::class);

        return match ($method) {
            PaymentMethod::CashOnDelivery => $settings->get('payments.cash_on_delivery') && $this->cash_on_delivery_enabled,
            PaymentMethod::Flutterwave,
            PaymentMethod::MobileMoney,
            PaymentMethod::Card => $settings->get('payments.online') && $this->hasActiveFlutterwaveConfiguration(),
        };
    }

    public function hasActiveFlutterwaveConfiguration(): bool
    {
        return $this->flutterwave_enabled
            && filled($this->flutterwave_secret_key)
            && filled($this->flutterwave_webhook_secret_hash);
    }
}

<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
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
        'delivery_enabled',
        'delivery_fee',
        'free_delivery_threshold',
        'delivery_zone',
        'delivery_delay',
        'pickup_enabled',
        'pickup_address',
    ];

    protected $casts = [
        'theme' => 'array',
        'tax_rate' => 'decimal:2',
        'flutterwave_secret_key' => 'encrypted',
        'flutterwave_webhook_secret_hash' => 'encrypted',
        'flutterwave_enabled' => 'boolean',
        'cash_on_delivery_enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'free_delivery_threshold' => 'decimal:2',
        'pickup_enabled' => 'boolean',
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
     * ERP applications installed on the shop (core modules are implicit).
     */
    public function modules(): HasMany
    {
        return $this->hasMany(ShopModule::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
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

    /**
     * @return list<string>
     */
    public function availableDeliveryMethods(): array
    {
        return array_values(array_filter([
            $this->delivery_enabled ? DeliveryMethod::Delivery->value : null,
            $this->pickup_enabled ? DeliveryMethod::Pickup->value : null,
        ]));
    }

    /**
     * Delivery fee for an order of the given amount (after discount); pickup is always free.
     */
    public function deliveryFeeFor(DeliveryMethod $method, float $amount): float
    {
        if ($method === DeliveryMethod::Pickup) {
            return 0.0;
        }

        $threshold = $this->free_delivery_threshold !== null ? (float) $this->free_delivery_threshold : null;

        return $threshold !== null && $amount >= $threshold ? 0.0 : (float) $this->delivery_fee;
    }

    /**
     * Plain-French description of the delivery options, for the customer-service assistant.
     */
    public function deliverySummary(): string
    {
        $options = [];

        if ($this->delivery_enabled) {
            $fee = (float) $this->delivery_fee > 0 ? number_format((float) $this->delivery_fee, 0, ',', ' ')." {$this->currency}" : 'offerte';
            $free = $this->free_delivery_threshold !== null && (float) $this->delivery_fee > 0
                ? ', offerte dès '.number_format((float) $this->free_delivery_threshold, 0, ',', ' ')." {$this->currency}"
                : '';
            $details = array_filter([$this->delivery_zone ? "zone : {$this->delivery_zone}" : null, $this->delivery_delay ? "délai : {$this->delivery_delay}" : null]);
            $options[] = "livraison à domicile ({$fee}{$free}".($details ? ' ; '.implode(', ', $details) : '').')';
        }

        if ($this->pickup_enabled) {
            $options[] = 'retrait gratuit en boutique'.($this->pickup_address ? " ({$this->pickup_address})" : '');
        }

        return $options ? ucfirst(implode(' ou ', $options)).'.' : 'Contactez la boutique pour organiser la remise de votre commande.';
    }

    /**
     * Plain-French list of the payment methods customers can actually use.
     */
    public function paymentSummary(): string
    {
        $methods = array_filter([
            $this->acceptsPaymentMethod(PaymentMethod::CashOnDelivery) ? 'à la livraison' : null,
            $this->acceptsPaymentMethod(PaymentMethod::Flutterwave) ? 'en ligne par Mobile Money ou carte bancaire' : null,
        ]);

        return $methods ? 'Vous pouvez payer '.implode(' ou ', $methods).'.' : 'Le paiement n’est pas encore ouvert sur cette boutique.';
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

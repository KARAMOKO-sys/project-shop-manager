<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'template' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('shopai.templates')))],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['sometimes', 'required', 'string', Rule::in(['XOF', 'XAF', 'EUR', 'USD', 'GNF', 'CDF', 'MAD'])],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'flutterwave_secret_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'flutterwave_webhook_secret_hash' => ['sometimes', 'nullable', 'string', 'max:255'],
            'flutterwave_enabled' => ['sometimes', 'boolean'],
            'cash_on_delivery_enabled' => ['sometimes', 'boolean'],
            ...self::themeRules(),
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['flutterwave_secret_key', 'flutterwave_webhook_secret_hash', 'flutterwave_enabled'])) {
                    return;
                }

                $shop = $this->route('shop');

                if (! $shop instanceof Shop) {
                    return;
                }

                $enabled = $this->has('flutterwave_enabled')
                    ? $this->boolean('flutterwave_enabled')
                    : $shop->flutterwave_enabled;
                $secretKey = filled($this->input('flutterwave_secret_key'))
                    ? $this->input('flutterwave_secret_key')
                    : $shop->flutterwave_secret_key;
                $webhookSecret = filled($this->input('flutterwave_webhook_secret_hash'))
                    ? $this->input('flutterwave_webhook_secret_hash')
                    : $shop->flutterwave_webhook_secret_hash;

                if ($enabled && (blank($secretKey) || blank($webhookSecret))) {
                    $validator->errors()->add('flutterwave_secret_key', 'Renseignez la clé secrète et le secret webhook avant d’activer Flutterwave.');
                }
            },
        ];
    }

    /**
     * Rules for the merchant's overrides of the template theme.
     *
     * @return array<string, array<mixed>>
     */
    public static function themeRules(): array
    {
        $color = ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'theme' => ['nullable', 'array:primary,secondary,background,surface,text,font,radius,hero_style,layout,format'],
            'theme.primary' => $color,
            'theme.secondary' => $color,
            'theme.background' => $color,
            'theme.surface' => $color,
            'theme.text' => $color,
            'theme.font' => ['sometimes', 'string', Rule::in(['Plus Jakarta Sans', 'Inter', 'Poppins', 'Montserrat', 'Nunito', 'DM Sans', 'Playfair Display', 'Lora', 'Space Grotesk'])],
            'theme.radius' => ['sometimes', 'string', 'regex:/^(0|\\d{1,2}(\\.\\d+)?(px|rem))$/'],
            'theme.hero_style' => ['sometimes', 'string', Rule::in(['gradient', 'image', 'minimal'])],
            'theme.layout' => ['sometimes', 'string', Rule::in(['grid', 'list', 'masonry'])],
            'theme.format' => ['sometimes', 'string', Rule::in(['classic', 'editorial', 'catalog', 'menu', 'showcase', 'single_product'])],
        ];
    }
}

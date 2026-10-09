<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            ...self::themeRules(),
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

<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Services\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('shop_id', $this->route('shop')->id)->ignore($this->route('product'))],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('shop_id', $this->route('shop')->id)],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'max:40'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('product') !== null) {
                    return;
                }

                $limit = (int) app(PlatformSettings::class)->get('limits.max_products_per_shop');

                if ($limit > 0 && $this->route('shop')->products()->count() >= $limit) {
                    $validator->errors()->add('name', "Cette boutique a atteint le nombre maximum de produits autorisé ({$limit}).");
                }
            },
        ];
    }
}

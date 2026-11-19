<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderFromSuggestionsRequest extends FormRequest
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
        $shopId = $this->route('shop')->id;

        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('shop_id', $shopId)],
            'product_ids' => ['nullable', 'array', 'max:500'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
        ];
    }
}

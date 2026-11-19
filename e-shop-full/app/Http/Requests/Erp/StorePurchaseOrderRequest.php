<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', Rule::exists('suppliers', 'id')->where('shop_id', $shopId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('shop_id', $shopId)],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}

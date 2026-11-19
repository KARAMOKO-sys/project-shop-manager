<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('shop_id', $shopId)],
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

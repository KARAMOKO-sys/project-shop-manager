<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryTransferRequest extends FormRequest
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
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('shop_id', $shopId)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('shop_id', $shopId)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePurchaseOrderRequest extends FormRequest
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
            'lines' => ['nullable', 'array', 'max:200'],
            'lines.*.id' => ['required', 'integer', Rule::exists('purchase_order_lines', 'id')->where('purchase_order_id', $this->route('purchase_order')->id)],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}

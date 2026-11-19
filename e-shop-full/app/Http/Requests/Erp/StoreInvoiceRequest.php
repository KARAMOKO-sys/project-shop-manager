<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('shop_id', $shopId)],
            'customer_name' => [$required, 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', Rule::when($this->filled('issue_date'), ['after_or_equal:issue_date'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => [$required, 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:-99999999', 'max:99999999'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}

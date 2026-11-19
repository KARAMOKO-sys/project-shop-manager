<?php

namespace App\Http\Requests\Erp;

use App\Enums\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'category' => [$required, Rule::enum(ExpenseCategory::class)],
            'label' => [$required, 'string', 'max:160'],
            'amount' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'spent_at' => [$required, 'date'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('shop_id', $this->route('shop')->id)],
        ];
    }
}

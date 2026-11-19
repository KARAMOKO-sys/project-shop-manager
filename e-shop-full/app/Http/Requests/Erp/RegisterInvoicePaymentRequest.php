<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterInvoicePaymentRequest extends FormRequest
{
    /**
     * Accepted payment methods.
     *
     * @var list<string>
     */
    public const METHODS = ['cash', 'mobile_money', 'card', 'bank_transfer', 'other'];

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
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'method' => ['required', Rule::in(self::METHODS)],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}

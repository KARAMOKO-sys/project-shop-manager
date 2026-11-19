<?php

namespace App\Http\Requests\Erp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryProductRequest extends FormRequest
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
            'reorder_point' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}

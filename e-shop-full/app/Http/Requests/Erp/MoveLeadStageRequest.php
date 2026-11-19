<?php

namespace App\Http\Requests\Erp;

use App\Enums\LeadStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveLeadStageRequest extends FormRequest
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
            'stage' => ['required', Rule::enum(LeadStage::class)],
            'lost_reason' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('stage') === LeadStage::Lost->value)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lost_reason.required' => 'Indiquez pourquoi l’opportunité est perdue : cela améliore vos prochaines ventes.',
        ];
    }
}

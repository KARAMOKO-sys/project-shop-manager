<?php

namespace App\Http\Requests\Erp;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The stage can only be
     * chosen at creation; afterwards it moves through the dedicated endpoint.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:160'],
            'contact_name' => [$required, 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => [$required, Rule::enum(LeadSource::class)],
            'expected_revenue' => [$creating ? 'nullable' : 'sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'stage' => $creating ? ['nullable', Rule::enum(LeadStage::class)->except([LeadStage::Won, LeadStage::Lost])] : ['prohibited'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShopRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'string', Rule::in(array_keys(config('shopai.templates')))],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sector' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', Rule::in(['XOF', 'XAF', 'EUR', 'USD', 'GNF', 'CDF', 'MAD'])],
            'categories' => ['nullable', 'array', 'max:12'],
            'categories.*' => ['string', 'max:60'],
            ...UpdateShopRequest::themeRules(),
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = (int) app(PlatformSettings::class)->get('limits.max_shops_per_user');

                if ($limit > 0 && $this->user()->shops()->count() >= $limit) {
                    $validator->errors()->add('name', "Vous avez atteint le nombre maximum de boutiques autorisé ({$limit}).");
                }
            },
        ];
    }
}

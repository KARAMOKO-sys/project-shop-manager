<?php

namespace App\Http\Requests;

use App\Services\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlatformSettingsRequest extends FormRequest
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
        return app(PlatformSettings::class)->rules();
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['moderation.review_threshold', 'moderation.reject_threshold'])) {
                    return;
                }

                $settings = app(PlatformSettings::class);
                $review = (int) $this->input('moderation.review_threshold', $settings->get('moderation.review_threshold'));
                $reject = (int) $this->input('moderation.reject_threshold', $settings->get('moderation.reject_threshold'));

                if ($reject <= $review) {
                    $validator->errors()->add('moderation.reject_threshold', 'Le seuil de refus doit être supérieur au seuil d’examen humain.');
                }
            },
        ];
    }
}

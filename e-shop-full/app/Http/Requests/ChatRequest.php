<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * The conversation so far, in the shape expected by the chat assistant.
     *
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        return collect($this->validated('history', []))
            ->map(fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']])
            ->values()
            ->all();
    }
}

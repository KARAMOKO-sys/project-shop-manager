<?php

namespace App\Services\AI;

/**
 * Used when no external model is configured: every call defers to the local engine.
 */
class NullLanguageModel implements LanguageModel
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'local';
    }

    public function structured(string $system, string $prompt, array $schema, string $effort = 'low'): ?array
    {
        return null;
    }

    public function chat(string $system, array $messages): ?string
    {
        return null;
    }
}

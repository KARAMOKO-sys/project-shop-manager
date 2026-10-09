<?php

namespace App\Services\AI;

interface LanguageModel
{
    /**
     * Whether the model can be called (e.g. credentials are configured).
     */
    public function isAvailable(): bool;

    /**
     * Short engine identifier stored alongside AI results.
     */
    public function name(): string;

    /**
     * Ask the model for a JSON object matching the given JSON schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null Null when the call failed or was declined.
     */
    public function structured(string $system, string $prompt, array $schema, string $effort = 'low'): ?array;

    /**
     * Continue a conversation and return the assistant's reply.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(string $system, array $messages): ?string;
}

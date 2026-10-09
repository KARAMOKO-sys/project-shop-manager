<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps one entry of config('shopai.templates'), with its key under "key".
 */
class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'name' => $this->resource['name'],
            'sector' => $this->resource['sector'],
            'description' => $this->resource['description'],
            'ideal_for' => $this->resource['ideal_for'],
            'theme' => $this->resource['theme'],
            'default_categories' => $this->resource['default_categories'],
        ];
    }

    /**
     * All templates from the configuration.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return collect(config('shopai.templates'))
            ->map(fn (array $template, string $key) => ['key' => $key, ...$template])
            ->values()
            ->all();
    }
}

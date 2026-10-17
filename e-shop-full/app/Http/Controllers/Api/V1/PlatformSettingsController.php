<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class PlatformSettingsController extends Controller
{
    public function __construct(private PlatformSettings $settings) {}

    /**
     * Public settings used by the frontend: branding, announcement and enabled features.
     */
    public function public(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicValues()]);
    }

    /**
     * Every setting with its definition, for the administration screen.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function update(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->validated());

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * @return array{groups: array<string, string>, definitions: array<string, mixed>, values: array<string, mixed>}
     */
    private function payload(): array
    {
        return [
            'groups' => PlatformSettings::GROUPS,
            'definitions' => collect($this->settings->definitions())
                ->map(fn (array $definition): array => Arr::except($definition, ['default']))
                ->all(),
            'values' => $this->settings->all(),
        ];
    }
}

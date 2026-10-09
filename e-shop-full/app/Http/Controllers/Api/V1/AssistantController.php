<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductDescriptionRequest;
use App\Http\Requests\ShopSetupRequest;
use App\Services\AI\ProductCopywriter;
use App\Services\AI\ShopSetupAssistant;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    /**
     * Propose a full shop configuration from a one-sentence description.
     */
    public function shopSetup(ShopSetupRequest $request, ShopSetupAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->suggest($request->validated('prompt'))]);
    }

    public function productDescription(ProductDescriptionRequest $request, ProductCopywriter $copywriter): JsonResponse
    {
        return response()->json([
            'data' => $copywriter->describe(
                $request->validated('name'),
                $request->validated('features'),
                $request->validated('tone'),
            ),
        ]);
    }
}

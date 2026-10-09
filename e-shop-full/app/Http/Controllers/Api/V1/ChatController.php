<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Http\Resources\ProductResource;
use App\Models\Shop;
use App\Services\AI\ChatAssistant;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(private ChatAssistant $assistant) {}

    /**
     * The platform assistant shown on the landing page and the merchant dashboard.
     */
    public function platform(ChatRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->assistant->platformReply($request->validated('message'), $request->history()),
        ]);
    }

    /**
     * The customer-service bot of a storefront.
     */
    public function shop(ChatRequest $request, Shop $shop): JsonResponse
    {
        abort_unless($shop->isActive(), 404);

        $answer = $this->assistant->shopReply($shop, $request->validated('message'), $request->history());

        return response()->json([
            'data' => [
                'reply' => $answer['reply'],
                'suggestions' => $answer['suggestions'],
                'products' => ProductResource::collection($answer['products']),
            ],
        ]);
    }
}

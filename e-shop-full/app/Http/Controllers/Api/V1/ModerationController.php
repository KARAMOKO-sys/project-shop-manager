<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerationDecisionRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Human review queue for products the moderation agent could not decide on.
 */
class ModerationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::enum(ModerationStatus::class)],
        ])['status'] ?? ModerationStatus::PendingReview->value;

        $products = Product::where('moderation_status', $status)
            ->with('shop', 'category')
            ->orderByDesc('moderation_risk_score')
            ->paginate(25);

        return ProductResource::collection($products);
    }

    public function decide(ModerationDecisionRequest $request, Product $product): ProductResource
    {
        $product->forceFill([
            'moderation_status' => $request->validated('decision') === 'approve' ? ModerationStatus::Approved : ModerationStatus::Rejected,
            'moderation_note' => $request->validated('note'),
            'moderation_engine' => 'human',
            'moderated_at' => now(),
        ])->save();

        return new ProductResource($product->load('shop', 'category'));
    }
}

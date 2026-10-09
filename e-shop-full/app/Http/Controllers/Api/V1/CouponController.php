<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckCouponRequest;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Shop $shop): AnonymousResourceCollection
    {
        return CouponResource::collection($shop->coupons()->latest()->get());
    }

    public function store(StoreCouponRequest $request, Shop $shop): JsonResponse
    {
        $coupon = $shop->coupons()->create([
            ...$request->validated(),
            'min_order_amount' => $request->validated('min_order_amount') ?? 0,
            'usage_limit' => $request->validated('usage_limit') ?? 0,
            'status' => $request->validated('status') ?? 'active',
        ]);

        return (new CouponResource($coupon))->response()->setStatusCode(201);
    }

    public function update(UpdateCouponRequest $request, Shop $shop, Coupon $coupon): CouponResource
    {
        $data = $request->validated();

        foreach (['min_order_amount', 'usage_limit'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }

        $coupon->update($data);

        return new CouponResource($coupon);
    }

    public function destroy(Shop $shop, Coupon $coupon): Response
    {
        $coupon->delete();

        return response()->noContent();
    }

    /**
     * Preview the discount of a code at checkout, before the order is placed.
     */
    public function check(CheckCouponRequest $request, Shop $shop): JsonResponse
    {
        abort_unless($shop->isActive(), 404);

        $subtotal = (float) $request->validated('subtotal');
        $coupon = $shop->coupons()->where('code', Str::upper($request->validated('code')))->first();

        if (! $coupon || ! $coupon->isRedeemableFor($subtotal)) {
            throw ValidationException::withMessages(['code' => 'Ce code promo est invalide ou expiré.']);
        }

        return response()->json([
            'data' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => (float) $coupon->value,
                'discount_amount' => $coupon->discountFor($subtotal),
            ],
        ]);
    }
}

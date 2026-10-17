<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\TrackOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\OrderService;
use App\Services\Payments\FlutterwavePaymentException;
use App\Services\Payments\FlutterwavePaymentService;
use App\Services\PlatformSettings;
use App\Services\RecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    public function show(Shop $shop): ShopResource
    {
        abort_unless($shop->isActive(), 404);

        return new ShopResource($shop->load('categories'));
    }

    public function products(Request $request, Shop $shop): AnonymousResourceCollection
    {
        abort_unless($shop->isActive(), 404);

        $products = $shop->products()
            ->publiclyVisible()
            ->with('shop', 'category')
            ->when($request->query('category'), fn (Builder $query, string $slug) => $query->whereRelation('category', 'slug', $slug))
            ->when($request->query('search'), fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_').'%')
                    ->orWhere('description', 'like', '%'.addcslashes($search, '%_').'%'),
            ))
            ->orderByDesc('is_featured')
            ->latest()
            ->paginate(24);

        return ProductResource::collection($products);
    }

    public function product(Shop $shop, string $productSlug): ProductResource
    {
        abort_unless($shop->isActive(), 404);

        $product = $shop->products()
            ->publiclyVisible()
            ->where('slug', $productSlug)
            ->with('shop', 'category')
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function recommendations(Shop $shop, string $productSlug, RecommendationService $recommendations, PlatformSettings $settings): AnonymousResourceCollection
    {
        abort_unless($shop->isActive(), 404);

        if (! $settings->featureEnabled('recommendations')) {
            return ProductResource::collection([]);
        }

        $product = $shop->products()->publiclyVisible()->where('slug', $productSlug)->firstOrFail();

        return ProductResource::collection($recommendations->forProduct($product));
    }

    /**
     * Order tracking for customers. The email acts as a lightweight proof of ownership.
     */
    public function order(TrackOrderRequest $request, Shop $shop, string $orderNumber): OrderResource
    {
        $order = $shop->orders()
            ->where('order_number', Str::upper($orderNumber))
            ->whereRelation('customer', 'email', Str::lower($request->validated('email')))
            ->with('customer', 'items')
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function checkout(
        CheckoutRequest $request,
        Shop $shop,
        OrderService $orders,
        FlutterwavePaymentService $payments,
    ): JsonResponse {
        abort_unless($shop->isActive(), 404);

        $payload = $request->validated();

        if (in_array($payload['payment_method'], [PaymentMethod::MobileMoney->value, PaymentMethod::Card->value], true)) {
            $payload['payment_method'] = PaymentMethod::Flutterwave->value;
        }

        $order = $orders->checkout($shop, $payload);

        if ($order->payment_method === PaymentMethod::Flutterwave) {
            try {
                $paymentUrl = $payments->startCheckout($order);
            } catch (FlutterwavePaymentException) {
                $orders->changeStatus($order, OrderStatus::Cancelled, 'failed');

                return response()->json([
                    'message' => 'Le paiement en ligne est temporairement indisponible. La commande a été annulée ; vous pouvez réessayer.',
                ], 502);
            }

            return (new OrderResource($order))
                ->additional(['payment_url' => $paymentUrl])
                ->response()
                ->setStatusCode(201);
        }

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\AnalyticsService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ShopController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $shops = $request->user()->shops()->withCount('products')->latest()->get();

        return ShopResource::collection($shops);
    }

    public function store(StoreShopRequest $request, ShopService $service): JsonResponse
    {
        $shop = $service->create($request->user(), $request->validated());

        return (new ShopResource($shop->load('categories')->loadCount('products')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Shop $shop): ShopResource
    {
        return new ShopResource($shop->load('categories')->loadCount('products'));
    }

    public function update(UpdateShopRequest $request, Shop $shop): ShopResource
    {
        $data = $request->validated();

        foreach (['flutterwave_secret_key', 'flutterwave_webhook_secret_hash'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        if (array_key_exists('theme', $data)) {
            $data['theme'] = $data['theme'] === null ? null : array_merge($shop->theme ?? [], $data['theme']);
        }

        if (isset($data['template']) && $data['template'] !== $shop->template && ! array_key_exists('theme', $data)) {
            $data['theme'] = null;
        }

        $shop->update($data);

        return new ShopResource($shop->load('categories')->loadCount('products'));
    }

    public function destroy(Shop $shop): Response
    {
        $shop->delete();

        return response()->noContent();
    }

    public function analytics(Shop $shop, AnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->forShop($shop)]);
    }
}

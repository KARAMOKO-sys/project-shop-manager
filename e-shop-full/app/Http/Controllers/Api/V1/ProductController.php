<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Jobs\ModerateProduct;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Shop $shop): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $shop->products()->with('shop', 'category')->latest()->paginate(25),
        );
    }

    public function store(StoreProductRequest $request, Shop $shop): JsonResponse
    {
        $product = $this->products->create($shop, $request->validated());

        return (new ProductResource($product->load('shop', 'category')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Shop $shop, Product $product): ProductResource
    {
        return new ProductResource($product->load('shop', 'category'));
    }

    public function update(UpdateProductRequest $request, Shop $shop, Product $product): ProductResource
    {
        $product = $this->products->update($product, $request->validated());

        return new ProductResource($product->load('shop', 'category'));
    }

    public function destroy(Shop $shop, Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * Run the moderation agent again, e.g. after the merchant fixed an issue.
     */
    public function moderate(Shop $shop, Product $product): ProductResource
    {
        ModerateProduct::dispatchSync($product);

        return new ProductResource($product->refresh()->load('shop', 'category'));
    }
}

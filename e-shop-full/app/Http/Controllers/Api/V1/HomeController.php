<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopResource;
use App\Http\Resources\TemplateResource;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeController extends Controller
{
    /**
     * Content of the platform landing page.
     */
    public function index(): JsonResponse
    {
        $featuredProducts = Product::publiclyVisible()
            ->with('shop', 'category')
            ->orderByDesc('is_featured')
            ->latest()
            ->limit(12)
            ->get();

        $shops = Shop::where('status', 'active')
            ->withCount(['products' => fn ($query) => $query->publiclyVisible()])
            ->orderByDesc('products_count')
            ->limit(6)
            ->get();

        return response()->json([
            'data' => [
                'featured_products' => ProductResource::collection($featuredProducts),
                'shops' => ShopResource::collection($shops),
                'templates' => TemplateResource::collection(TemplateResource::catalog()),
                'stats' => [
                    'shops' => Shop::where('status', 'active')->count(),
                    'products' => Product::publiclyVisible()->count(),
                ],
            ],
        ]);
    }

    public function templates(): AnonymousResourceCollection
    {
        return TemplateResource::collection(TemplateResource::catalog());
    }
}

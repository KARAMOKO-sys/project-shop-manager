<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Shop $shop): AnonymousResourceCollection
    {
        return CategoryResource::collection($shop->categories()->orderBy('name')->get());
    }

    public function store(StoreCategoryRequest $request, Shop $shop): JsonResponse
    {
        $name = $request->validated('name');
        $slug = Str::slug($name);

        if ($shop->categories()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Cette catégorie existe déjà.']);
        }

        $category = $shop->categories()->create(['name' => $name, 'slug' => $slug]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }
}

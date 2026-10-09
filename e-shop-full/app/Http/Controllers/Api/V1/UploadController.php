<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Store a product photo or logo in the shop's public folder.
     */
    public function store(UploadImageRequest $request, Shop $shop): JsonResponse
    {
        $path = $request->file('image')->store("shops/{$shop->id}", 'public');

        return response()->json(['data' => ['url' => Storage::disk('public')->url($path)]], 201);
    }
}

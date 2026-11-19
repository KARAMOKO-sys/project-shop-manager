<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\StoreSupplierRequest;
use App\Http\Resources\Erp\SupplierResource;
use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Shop $shop): AnonymousResourceCollection
    {
        return SupplierResource::collection($shop->suppliers()->withCount('purchaseOrders')->orderBy('name')->get());
    }

    public function store(StoreSupplierRequest $request, Shop $shop): JsonResponse
    {
        $data = $request->validated();

        $supplier = $shop->suppliers()->create([
            ...$data,
            'payment_terms_days' => $data['payment_terms_days'] ?? 0,
            'lead_time_days' => $data['lead_time_days'] ?? (int) config('erp.inventory.default_lead_time_days'),
        ]);

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(StoreSupplierRequest $request, Shop $shop, Supplier $supplier): SupplierResource
    {
        $data = $request->validated();

        foreach (['payment_terms_days' => 0, 'lead_time_days' => (int) config('erp.inventory.default_lead_time_days')] as $field => $default) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = $default;
            }
        }

        $supplier->update($data);

        return new SupplierResource($supplier->loadCount('purchaseOrders'));
    }

    public function destroy(Shop $shop, Supplier $supplier): Response
    {
        if ($supplier->purchaseOrders()->exists()) {
            throw ValidationException::withMessages(['supplier' => 'Ce fournisseur a des bons de commande : il ne peut pas être supprimé.']);
        }

        $supplier->delete();

        return response()->noContent();
    }
}

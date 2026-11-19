<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\StoreWarehouseRequest;
use App\Http\Resources\Erp\WarehouseResource;
use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    public function index(Shop $shop): AnonymousResourceCollection
    {
        return WarehouseResource::collection(
            $shop->warehouses()->withCount('stocks')->withSum('stocks', 'quantity')->orderByDesc('is_default')->orderBy('name')->get(),
        );
    }

    public function store(StoreWarehouseRequest $request, Shop $shop): JsonResponse
    {
        $warehouse = DB::transaction(function () use ($request, $shop): Warehouse {
            $isDefault = (bool) $request->validated('is_default') || $shop->warehouses()->doesntExist();

            if ($isDefault) {
                $shop->warehouses()->update(['is_default' => false]);
            }

            return $shop->warehouses()->create([...$request->validated(), 'is_default' => $isDefault]);
        });

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function update(StoreWarehouseRequest $request, Shop $shop, Warehouse $warehouse): WarehouseResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($shop, $warehouse, $data): void {
            if (array_key_exists('is_default', $data)) {
                if ($data['is_default']) {
                    $shop->warehouses()->whereKeyNot($warehouse->id)->update(['is_default' => false]);
                } elseif ($warehouse->is_default) {
                    throw ValidationException::withMessages(['is_default' => 'Choisissez un autre entrepôt par défaut plutôt que de retirer ce statut.']);
                }
            }

            $warehouse->update($data);
        });

        return new WarehouseResource($warehouse->loadCount('stocks')->loadSum('stocks', 'quantity'));
    }

    public function destroy(Shop $shop, Warehouse $warehouse): Response
    {
        if ($warehouse->is_default) {
            throw ValidationException::withMessages(['warehouse' => 'L’entrepôt par défaut ne peut pas être supprimé.']);
        }

        if ($warehouse->stocks()->where('quantity', '!=', 0)->exists()) {
            throw ValidationException::withMessages(['warehouse' => 'Cet entrepôt contient encore du stock : transférez-le avant de le supprimer.']);
        }

        if ($shop->purchaseOrders()->where('warehouse_id', $warehouse->id)->exists()) {
            throw ValidationException::withMessages(['warehouse' => 'Cet entrepôt est utilisé par des bons de commande.']);
        }

        $warehouse->delete();

        return response()->noContent();
    }
}

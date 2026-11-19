<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\InventoryAdjustmentRequest;
use App\Http\Requests\Erp\InventoryTransferRequest;
use App\Http\Requests\Erp\UpdateInventoryProductRequest;
use App\Http\Resources\Erp\InventoryStockResource;
use App\Http\Resources\Erp\StockMovementResource;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Erp\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Stock position of every product, split per warehouse.
     */
    public function stock(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('shop_id', $shop->id)],
        ]);

        $products = $shop->products()
            ->with(['stocks' => fn ($query) => $query->with('warehouse')->when($request->filled('warehouse_id'), fn ($stocks) => $stocks->where('warehouse_id', $request->integer('warehouse_id')))])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'like', '%'.$request->query('search').'%')
                ->orWhere('sku', 'like', '%'.$request->query('search').'%')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->whereHas('stocks', fn ($stocks) => $stocks->where('warehouse_id', $request->integer('warehouse_id'))))
            ->orderBy('name')
            ->paginate(25);

        return InventoryStockResource::collection($products);
    }

    public function movements(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
        ]);

        $movements = $shop->stockMovements()
            ->with('product', 'warehouse', 'user')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->latest('id')
            ->paginate(25);

        return StockMovementResource::collection($movements);
    }

    public function suggestions(Shop $shop): JsonResponse
    {
        return response()->json(['data' => $this->inventory->reorderSuggestions($shop)]);
    }

    public function adjust(InventoryAdjustmentRequest $request, Shop $shop): JsonResponse
    {
        $movement = $this->inventory->adjust(
            $shop->products()->findOrFail($request->validated('product_id')),
            $shop->warehouses()->findOrFail($request->validated('warehouse_id')),
            (int) $request->validated('counted_quantity'),
            $request->validated('note'),
        );

        if ($movement === null) {
            return response()->json(['data' => null]);
        }

        return (new StockMovementResource($movement->load('product', 'warehouse', 'user')))->response()->setStatusCode(201);
    }

    public function transfer(InventoryTransferRequest $request, Shop $shop): JsonResponse
    {
        $movements = $this->inventory->transfer(
            $shop->products()->findOrFail($request->validated('product_id')),
            $shop->warehouses()->findOrFail($request->validated('from_warehouse_id')),
            $shop->warehouses()->findOrFail($request->validated('to_warehouse_id')),
            (int) $request->validated('quantity'),
            $request->validated('note'),
        );

        return StockMovementResource::collection(collect($movements)->each->load('product', 'warehouse', 'user'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reorder point and cost price, the two inventory settings of a product.
     */
    public function updateProduct(UpdateInventoryProductRequest $request, Shop $shop, Product $product): InventoryStockResource
    {
        $product->forceFill($request->validated())->save();

        return new InventoryStockResource($product->load('stocks.warehouse'));
    }
}

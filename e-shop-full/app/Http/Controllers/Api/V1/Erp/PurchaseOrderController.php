<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\PurchaseOrderFromSuggestionsRequest;
use App\Http\Requests\Erp\ReceivePurchaseOrderRequest;
use App\Http\Requests\Erp\StorePurchaseOrderRequest;
use App\Http\Resources\Erp\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Services\Erp\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)]]);

        return PurchaseOrderResource::collection(
            $shop->purchaseOrders()
                ->with('supplier', 'warehouse')
                ->withCount('lines')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->latest('id')
                ->paginate(25),
        );
    }

    public function store(StorePurchaseOrderRequest $request, Shop $shop): JsonResponse
    {
        $order = $this->purchases->create($shop, $request->validated(), $request->user());

        return (new PurchaseOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Shop $shop, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder->load('supplier', 'warehouse', 'lines.product'));
    }

    public function update(StorePurchaseOrderRequest $request, Shop $shop, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchases->update($purchaseOrder, $request->validated()));
    }

    public function confirm(Shop $shop, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchases->confirm($purchaseOrder));
    }

    public function receive(ReceivePurchaseOrderRequest $request, Shop $shop, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $lines = $request->validated('lines');
        $quantities = $lines === null ? null : collect($lines)->mapWithKeys(fn (array $line): array => [(int) $line['id'] => (int) $line['quantity']])->all();

        return new PurchaseOrderResource($this->purchases->receive($purchaseOrder, $quantities));
    }

    public function cancel(Shop $shop, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchases->cancel($purchaseOrder));
    }

    /**
     * One-click replenishment from the inventory suggestions.
     */
    public function fromSuggestions(PurchaseOrderFromSuggestionsRequest $request, Shop $shop): JsonResponse
    {
        $orders = $this->purchases->createFromSuggestions(
            $shop,
            $request->filled('supplier_id') ? $shop->suppliers()->findOrFail($request->validated('supplier_id')) : null,
            $request->has('product_ids') ? array_map('intval', $request->validated('product_ids') ?? []) : null,
            $request->user(),
        );

        return PurchaseOrderResource::collection($orders)->response()->setStatusCode($orders === [] ? 200 : 201);
    }
}

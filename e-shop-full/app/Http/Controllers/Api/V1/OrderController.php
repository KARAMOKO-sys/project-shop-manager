<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Shop $shop): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $shop->orders()->with('customer', 'items')->latest()->paginate(25),
        );
    }

    public function update(UpdateOrderStatusRequest $request, Shop $shop, Order $order, OrderService $orders): OrderResource
    {
        $order = $orders->changeStatus($order->load('items'), OrderStatus::from($request->validated('status')));

        return new OrderResource($order->load('customer', 'items'));
    }
}

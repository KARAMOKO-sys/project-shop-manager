<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\RegisterInvoicePaymentRequest;
use App\Http\Requests\Erp\StoreInvoiceRequest;
use App\Http\Requests\Erp\UpdateInvoiceRequest;
use App\Http\Resources\Erp\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Erp\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $request->query('search');

        return InvoiceResource::collection(
            $shop->invoices()
                ->with('order:id,order_number')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->when(filled($search), fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate(25),
        );
    }

    public function store(StoreInvoiceRequest $request, Shop $shop): JsonResponse
    {
        $invoice = $this->invoices->create($shop, $request->validated());

        return (new InvoiceResource($invoice->load('order:id,order_number')))->response()->setStatusCode(201);
    }

    public function show(Shop $shop, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('lines', 'payments', 'order:id,order_number'));
    }

    public function update(UpdateInvoiceRequest $request, Shop $shop, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoices->update($invoice, $request->validated());

        return new InvoiceResource($invoice->load('order:id,order_number'));
    }

    /**
     * Orders that can still be invoiced: not cancelled and without invoice.
     */
    public function invoiceableOrders(Shop $shop): JsonResponse
    {
        $orders = $shop->orders()
            ->with('customer:id,first_name,last_name')
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotIn('id', Invoice::query()->whereNotNull('order_id')->select('order_id'))
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? '')),
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ]),
        ]);
    }

    public function fromOrder(Shop $shop, Order $order): JsonResponse
    {
        $invoice = $this->invoices->createFromOrder($order);

        return (new InvoiceResource($invoice->load('order:id,order_number')))->response()->setStatusCode(201);
    }

    public function post(Shop $shop, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->post($invoice)->load('order:id,order_number'));
    }

    public function registerPayment(RegisterInvoicePaymentRequest $request, Shop $shop, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoices->registerPayment($invoice, $request->validated(), $request->user());

        return new InvoiceResource($invoice->load('order:id,order_number'));
    }

    public function cancel(Shop $shop, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->cancel($invoice)->load('order:id,order_number'));
    }
}

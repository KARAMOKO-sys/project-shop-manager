<?php

namespace App\Services\Erp;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Cross-application cockpit: one screen with the key figures of every
 * installed module, including the real profit once expenses are tracked.
 */
class ErpDashboardService
{
    public function __construct(
        private ModuleRegistry $modules,
        private InventoryService $inventory,
        private CrmService $crm,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forShop(Shop $shop, int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();
        $installed = $this->modules->installed($shop);

        $orders = $shop->orders()->where('created_at', '>=', $since)->where('status', '!=', OrderStatus::Cancelled);
        $revenue = round((float) (clone $orders)->sum('total'), 2);

        $costOfGoods = round((float) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.created_at', '>=', $since)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->sum(DB::raw('order_items.quantity * COALESCE(products.cost_price, 0)')), 2);

        $data = [
            'period_days' => $days,
            'currency' => $shop->currency,
            'modules' => $installed,
            'sales' => [
                'revenue' => $revenue,
                'orders_count' => (clone $orders)->count(),
                'average_order' => (clone $orders)->count() > 0 ? round($revenue / (clone $orders)->count(), 2) : 0.0,
                'cost_of_goods' => $costOfGoods,
                'gross_margin' => round($revenue - $costOfGoods, 2),
                'to_process' => $shop->orders()->whereIn('status', [OrderStatus::Received, OrderStatus::Paid, OrderStatus::Processing])->count(),
            ],
        ];

        if (in_array('inventory', $installed, true)) {
            $suggestions = $this->inventory->reorderSuggestions($shop);

            $data['inventory'] = [
                'stock_value' => $this->inventory->stockValue($shop),
                'warehouses_count' => $shop->warehouses()->count(),
                'out_of_stock_count' => $shop->products()->where('stock_quantity', '<=', 0)->count(),
                'reorder_count' => count($suggestions),
                'critical_count' => count(array_filter($suggestions, fn (array $item): bool => $item['urgency'] === 'critical')),
            ];
        }

        if (in_array('purchase', $installed, true)) {
            $open = $shop->purchaseOrders()->whereIn('status', [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived]);

            $data['purchase'] = [
                'open_count' => (clone $open)->count(),
                'open_amount' => round((float) (clone $open)->sum('total'), 2),
                'late_count' => (clone $open)->whereDate('expected_at', '<', today())->count(),
                'suppliers_count' => $shop->suppliers()->count(),
            ];
        }

        if (in_array('invoicing', $installed, true)) {
            $openInvoices = $shop->invoices()->whereIn('status', [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid]);

            $data['invoicing'] = [
                'receivables' => round((float) (clone $openInvoices)->sum(DB::raw('total - amount_paid')), 2),
                'overdue_amount' => round((float) (clone $openInvoices)->whereDate('due_date', '<', today())->sum(DB::raw('total - amount_paid')), 2),
                'overdue_count' => (clone $openInvoices)->whereDate('due_date', '<', today())->count(),
                'invoiced' => round((float) $shop->invoices()->where('status', '!=', InvoiceStatus::Cancelled)->whereNotNull('posted_at')->where('issue_date', '>=', $since)->sum('total'), 2),
                'collected' => round((float) DB::table('invoice_payments')->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')->where('invoices.shop_id', $shop->id)->where('invoice_payments.paid_at', '>=', $since)->sum('invoice_payments.amount'), 2),
            ];
        }

        if (in_array('expenses', $installed, true)) {
            $expenses = round((float) $shop->expenses()->where('spent_at', '>=', $since)->sum('amount'), 2);

            $data['expenses'] = [
                'total' => $expenses,
                'by_category' => $shop->expenses()->where('spent_at', '>=', $since)->groupBy('category')->selectRaw('category, SUM(amount) as total')->pluck('total', 'category')->map(fn ($sum): float => round((float) $sum, 2)),
                'net_profit' => round($revenue - $costOfGoods - $expenses, 2),
            ];
        }

        if (in_array('crm', $installed, true)) {
            $data['crm'] = $this->crm->pipeline($shop);
        }

        $data['recent_activity'] = $shop->activities()->with('user:id,name')->latest('id')->limit(8)->get()->map(fn ($activity): array => [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'user' => $activity->user?->name,
            'created_at' => $activity->created_at,
        ]);

        return $data;
    }
}

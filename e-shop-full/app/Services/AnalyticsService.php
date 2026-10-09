<?php

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    /**
     * Published products at or below this quantity are reported as low on stock.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * Key performance indicators of a shop. Cancelled orders are excluded from revenue.
     *
     * @return array{revenue: float, orders: int, customers: int, products: int, average_order_value: float, pending_moderation: int, orders_by_status: array<string, int>, low_stock_products: list<array{id: int, name: string, stock_quantity: int}>, revenue_by_day: list<array{date: string, revenue: float, orders: int}>}
     */
    public function forShop(Shop $shop): array
    {
        $ordersByStatus = $shop->orders()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $validOrders = $shop->orders()->where('status', '!=', OrderStatus::Cancelled);
        $orderCount = (clone $validOrders)->count();
        $revenue = (float) (clone $validOrders)->sum('total');

        return [
            'revenue' => $revenue,
            'orders' => $orderCount,
            'customers' => $shop->customers()->count(),
            'products' => $shop->products()->count(),
            'average_order_value' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0.0,
            'pending_moderation' => $shop->products()
                ->whereIn('moderation_status', [ModerationStatus::Pending, ModerationStatus::PendingReview])
                ->count(),
            'orders_by_status' => $ordersByStatus->all(),
            'low_stock_products' => $this->lowStockProducts($shop),
            'revenue_by_day' => $this->revenueByDay($shop, 30),
        ];
    }

    /**
     * @return list<array{id: int, name: string, stock_quantity: int}>
     */
    private function lowStockProducts(Shop $shop): array
    {
        return $shop->products()
            ->where('status', ProductStatus::Published)
            ->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD)
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'stock_quantity'])
            ->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'stock_quantity' => $product->stock_quantity])
            ->all();
    }

    /**
     * Daily revenue and order count, with a row for every day of the period.
     *
     * @return list<array{date: string, revenue: float, orders: int}>
     */
    private function revenueByDay(Shop $shop, int $days): array
    {
        $since = Carbon::today()->subDays($days - 1);

        $rows = $shop->orders()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($since, $rows) {
                $date = $since->copy()->addDays($offset)->toDateString();

                return [
                    'date' => $date,
                    'revenue' => (float) ($rows[$date]->revenue ?? 0),
                    'orders' => (int) ($rows[$date]->orders ?? 0),
                ];
            })
            ->all();
    }
}

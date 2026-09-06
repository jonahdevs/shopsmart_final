<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminDashboardStatsData;
use App\Data\AdminOrderRowData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Settings\InventorySettings;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store at a glance.
 *
 * Every figure on this page is an aggregate — no page here hydrates a
 * collection to count it. The tiles compare a trailing window against the
 * window before it, so "revenue" always means the same span of days on both
 * sides of the comparison.
 *
 * Revenue counts paid orders only; see {@see AdminDashboardStatsData}.
 */
class DashboardController extends Controller
{
    /** The trailing window every tile is measured over. */
    private const WINDOW_DAYS = 30;

    /** How many recent orders the overview lists. */
    private const RECENT_ORDERS = 8;

    public function __invoke(): Response
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);
        $previousStart = $now->copy()->subDays(self::WINDOW_DAYS * 2);

        return Inertia::render('admin/Dashboard', [
            'stats' => $this->stats($windowStart, $previousStart, $now),
            'recentOrders' => $this->recentOrders(),
        ]);
    }

    private function stats(Carbon $windowStart, Carbon $previousStart, Carbon $now): AdminDashboardStatsData
    {
        $current = $this->paidTotals($windowStart, $now);
        $previous = $this->paidTotals($previousStart, $windowStart);

        $paidCount = $current['count'];
        $revenue = $current['revenue'];

        return new AdminDashboardStatsData(
            revenueCents: $revenue,
            revenueFormatted: money($revenue),
            revenueChangePercent: AdminDashboardStatsData::changePercent($revenue, $previous['revenue']),
            paidOrderCount: $paidCount,
            paidOrderChangePercent: AdminDashboardStatsData::changePercent($paidCount, $previous['count']),
            // Integer division on purpose: this is money, and a fractional cent
            // has nowhere to go. Guarded because a month with no sales is a
            // perfectly ordinary state for a new store.
            averageOrderValueCents: $paidCount > 0 ? intdiv($revenue, $paidCount) : 0,
            averageOrderValueFormatted: money($paidCount > 0 ? intdiv($revenue, $paidCount) : 0),
            newCustomerCount: User::query()
                ->whereDoesntHave('roles')
                ->whereBetween('created_at', [$windowStart, $now])
                ->count(),
            awaitingPaymentCount: Order::query()
                ->where('payment_status', PaymentStatus::Pending)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->count(),
            awaitingFulfilmentCount: Order::query()
                ->where('payment_status', PaymentStatus::Success)
                ->whereIn('status', [OrderStatus::Processing, OrderStatus::OutForDelivery])
                ->count(),
            lowStockCount: $this->lowStockCount(),
            periodLabel: __('Last :days days', ['days' => self::WINDOW_DAYS]),
        );
    }

    /**
     * Revenue and order count for paid orders settled inside a window.
     *
     * Measured on `paid_at`, not `placed_at`: an order placed in one month and
     * collected in the next is revenue for the month the money arrived.
     *
     * @return array{revenue: int, count: int}
     */
    private function paidTotals(Carbon $from, Carbon $to): array
    {
        $row = Order::query()
            ->where('payment_status', PaymentStatus::Success)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(total_cents), 0) as revenue, COUNT(*) as orders')
            ->first();

        return [
            'revenue' => (int) ($row?->getAttribute('revenue') ?? 0),
            'count' => (int) ($row?->getAttribute('orders') ?? 0),
        ];
    }

    /**
     * Tracked products at or below the store's low-stock threshold.
     *
     * A null `stock_quantity` means the product is not stock-tracked at all and
     * can never be low; the column comparison excludes those rows on its own.
     */
    private function lowStockCount(): int
    {
        return Product::query()
            ->whereNotNull('stock_quantity')
            ->where('stock_quantity', '<=', app(InventorySettings::class)->low_stock_threshold)
            ->count();
    }

    /**
     * @return list<AdminOrderRowData>
     */
    private function recentOrders(): array
    {
        return array_values(Order::query()
            ->withSum('items', 'quantity')
            ->orderByDesc('placed_at')
            ->take(self::RECENT_ORDERS)
            ->get()
            ->map(fn (Order $order): AdminOrderRowData => AdminOrderRowData::fromModel($order))
            ->all());
    }
}

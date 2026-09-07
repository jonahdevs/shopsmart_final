<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminChartSliceData;
use App\Data\AdminDashboardChartsData;
use App\Data\AdminDashboardStatsData;
use App\Data\AdminDashboardTimelineData;
use App\Data\AdminOrderRowData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Settings\CurrencySettings;
use App\Settings\InventorySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
 *
 * The charts are deferred. The tiles are six cheap aggregates and should paint
 * on the first response; the six breakdowns below them each cost a grouped scan
 * over orders or order items, and holding the whole page back for them makes a
 * fast dashboard feel slow.
 */
class DashboardController extends Controller
{
    /** The trailing window every tile is measured over. */
    private const WINDOW_DAYS = 30;

    /** How many recent orders the overview lists. */
    private const RECENT_ORDERS = 8;

    /** Rows in a "top N" breakdown. Beyond this a chart stops being readable. */
    private const TOP_N = 6;

    public function __invoke(): Response
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);
        $previousStart = $now->copy()->subDays(self::WINDOW_DAYS * 2);

        return Inertia::render('admin/Dashboard', [
            'stats' => $this->stats($windowStart, $previousStart, $now),
            'recentOrders' => $this->recentOrders(),
            'charts' => Inertia::defer(
                fn (): AdminDashboardChartsData => $this->charts($windowStart, $now)
            ),
        ]);
    }

    private function stats(Carbon $windowStart, Carbon $previousStart, Carbon $now): AdminDashboardStatsData
    {
        $current = $this->paidTotals($windowStart, $now);
        $previous = $this->paidTotals($previousStart, $windowStart);

        $paidCount = $current['count'];
        $revenue = $current['revenue'];

        $newCustomers = $this->newCustomerCount($windowStart, $now);
        $previousCustomers = $this->newCustomerCount($previousStart, $windowStart);

        // Integer division on purpose: this is money, and a fractional cent has
        // nowhere to go. Guarded because a month with no sales is a perfectly
        // ordinary state for a new store.
        $averageOrderValue = $paidCount > 0 ? intdiv($revenue, $paidCount) : 0;
        $previousAverage = $previous['count'] > 0
            ? intdiv($previous['revenue'], $previous['count'])
            : 0;

        return new AdminDashboardStatsData(
            revenueCents: $revenue,
            revenueFormatted: money($revenue),
            revenueChangePercent: AdminDashboardStatsData::changePercent($revenue, $previous['revenue']),
            paidOrderCount: $paidCount,
            paidOrderChangePercent: AdminDashboardStatsData::changePercent($paidCount, $previous['count']),
            averageOrderValueCents: $averageOrderValue,
            averageOrderValueFormatted: money($averageOrderValue),
            averageOrderValueChangePercent: AdminDashboardStatsData::changePercent($averageOrderValue, $previousAverage),
            newCustomerCount: $newCustomers,
            newCustomerChangePercent: AdminDashboardStatsData::changePercent($newCustomers, $previousCustomers),
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
     * Registrations in a window. "Customer" is a user holding no role at all —
     * staff are excluded by their role, not by an is_staff flag.
     */
    private function newCustomerCount(Carbon $from, Carbon $to): int
    {
        return User::query()
            ->whereDoesntHave('roles')
            ->whereBetween('created_at', [$from, $to])
            ->count();
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

    private function charts(Carbon $windowStart, Carbon $now): AdminDashboardChartsData
    {
        $ratings = $this->ratingBreakdown();

        return new AdminDashboardChartsData(
            timeline: $this->timeline($windowStart, $now),
            ordersByStatus: $this->ordersByStatus($windowStart, $now),
            revenueByMethod: $this->revenueByMethod($windowStart, $now),
            topProducts: $this->topProducts($windowStart, $now),
            topCategories: $this->topCategories($windowStart, $now),
            ratings: $ratings['slices'],
            averageRating: $ratings['average'],
            reviewCount: $ratings['total'],
        );
    }

    /**
     * Revenue, paid orders and registrations bucketed by day.
     *
     * Built by walking the window and reading from keyed aggregates rather than
     * by iterating query rows: days with no trading do not appear in a GROUP BY
     * result, and a line chart that simply skips them draws a slope between two
     * distant days and invents trading that did not happen.
     */
    private function timeline(Carbon $windowStart, Carbon $now): AdminDashboardTimelineData
    {
        $revenue = $this->dailyTotals(
            Order::query()
                ->where('payment_status', PaymentStatus::Success)
                ->whereBetween('paid_at', [$windowStart, $now]),
            'DATE(paid_at) as bucket, COALESCE(SUM(total_cents), 0) as total',
        );

        $orders = $this->dailyTotals(
            Order::query()
                ->where('payment_status', PaymentStatus::Success)
                ->whereBetween('paid_at', [$windowStart, $now]),
            'DATE(paid_at) as bucket, COUNT(*) as total',
        );

        $customers = $this->dailyTotals(
            User::query()
                ->whereDoesntHave('roles')
                ->whereBetween('created_at', [$windowStart, $now]),
            'DATE(created_at) as bucket, COUNT(*) as total',
        );

        $labels = [];
        $revenueSeries = [];
        $orderSeries = [];
        $customerSeries = [];

        for ($day = $windowStart->copy()->startOfDay(); $day->lte($now); $day->addDay()) {
            $key = $day->toDateString();

            $labels[] = $day->format('j M');
            $revenueSeries[] = round(($revenue[$key] ?? 0) / 100, 2);
            $orderSeries[] = (int) ($orders[$key] ?? 0);
            $customerSeries[] = (int) ($customers[$key] ?? 0);
        }

        return new AdminDashboardTimelineData(
            labels: $labels,
            revenue: $revenueSeries,
            orders: $orderSeries,
            customers: $customerSeries,
            currencySymbol: app(CurrencySettings::class)->symbol,
        );
    }

    /**
     * One aggregate per calendar day, keyed by `Y-m-d`.
     *
     * The select is a `literal-string` rather than a column plus an aggregate
     * assembled here. The moment this method interpolates anything into SQL it
     * becomes a place a caller could route user input through, and the type
     * system can no longer tell the difference. Callers pass a whole literal
     * instead, which costs a few repeated characters and makes the compiler
     * enforce what a docblock would otherwise only promise.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  literal-string  $select  Must yield `bucket` and `total` columns.
     * @return array<string, int>
     */
    private function dailyTotals(Builder $query, string $select): array
    {
        /** @var Collection<int, object{bucket: string, total: mixed}> $rows */
        $rows = $query
            ->selectRaw($select)
            ->groupBy('bucket')
            ->get();

        return $rows
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->bucket => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Orders in the window split by fulfilment status.
     *
     * Iterates the enum rather than the result rows so a status with no orders
     * still appears as a zero — a status chart that silently omits "Cancelled"
     * reads as a store with no cancellations.
     *
     * @return list<AdminChartSliceData>
     */
    private function ordersByStatus(Carbon $windowStart, Carbon $now): array
    {
        /** @var array<string, int> $counts */
        $counts = Order::query()
            ->whereBetween('placed_at', [$windowStart, $now])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return array_map(
            fn (OrderStatus $status): AdminChartSliceData => AdminChartSliceData::count(
                $status->label(),
                $counts[$status->value] ?? 0,
            ),
            OrderStatus::cases(),
        );
    }

    /**
     * Captured revenue split by how it was paid.
     *
     * `payment_method` is a free string rather than an enum — the gateway
     * writes it — so an unrecognised value is titled rather than dropped. A
     * method the store starts accepting tomorrow should show up on this chart
     * without a deploy.
     *
     * @return list<AdminChartSliceData>
     */
    private function revenueByMethod(Carbon $windowStart, Carbon $now): array
    {
        /** @var Collection<int, object{payment_method: ?string, revenue: mixed}> $rows */
        $rows = Order::query()
            ->where('payment_status', PaymentStatus::Success)
            ->whereBetween('paid_at', [$windowStart, $now])
            ->selectRaw('payment_method, COALESCE(SUM(total_cents), 0) as revenue')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get();

        return array_values($rows
            ->map(fn (object $row): AdminChartSliceData => AdminChartSliceData::money(
                $this->methodLabel($row->payment_method),
                (int) $row->revenue,
            ))
            ->all());
    }

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            null, '' => __('Unrecorded'),
            'paystack' => __('Card / M-Pesa'),
            'bank_transfer' => __('Bank transfer'),
            'cash_on_delivery' => __('Cash on delivery'),
            default => str($method)->replace('_', ' ')->title()->value(),
        };
    }

    /**
     * Best sellers by units shipped, cancellations excluded.
     *
     * Grouped on the line item's own `name` snapshot, not on the product's
     * current name: an item renamed last week still sold under the name it had,
     * and joining to `products` would also silently drop anything since
     * deleted.
     *
     * @return list<AdminChartSliceData>
     */
    private function topProducts(Carbon $windowStart, Carbon $now): array
    {
        /** @var Collection<int, object{name: string, units: mixed}> $rows */
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.placed_at', [$windowStart, $now])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.name as name, SUM(order_items.quantity) as units')
            ->groupBy('order_items.name')
            ->orderByDesc('units')
            ->limit(self::TOP_N)
            ->get();

        return array_values($rows
            ->map(fn (object $row): AdminChartSliceData => AdminChartSliceData::count(
                $row->name,
                (int) $row->units,
                __('units'),
            ))
            ->all());
    }

    /**
     * Best selling categories by units shipped.
     *
     * A product may sit in several categories, so its units are counted once
     * per category it belongs to. That means these figures sum to more than the
     * order total — which is correct for "which aisles are selling" and would
     * be wrong for "what did we sell", so the chart is labelled accordingly.
     *
     * @return list<AdminChartSliceData>
     */
    private function topCategories(Carbon $windowStart, Carbon $now): array
    {
        /** @var Collection<int, object{name: string, units: mixed}> $rows */
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('category_product', 'category_product.product_id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->whereBetween('orders.placed_at', [$windowStart, $now])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('categories.name as name, SUM(order_items.quantity) as units')
            ->groupBy('categories.name')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        return array_values($rows
            ->map(fn (object $row): AdminChartSliceData => AdminChartSliceData::count(
                $row->name,
                (int) $row->units,
                __('units'),
            ))
            ->all());
    }

    /**
     * Approved reviews per star, five down to one, with the mean.
     *
     * Lifetime rather than windowed: a store's reputation is not a 30-day
     * figure, and a month with four reviews would swing the average wildly.
     *
     * @return array{slices: list<AdminChartSliceData>, average: ?float, total: int}
     */
    private function ratingBreakdown(): array
    {
        /** @var array<int, int> $counts */
        $counts = Review::query()
            ->where('status', ReviewStatus::Approved)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->mapWithKeys(fn (mixed $total, mixed $rating): array => [
                (int) $rating => (int) $total,
            ])
            ->all();

        $slices = [];
        $total = 0;
        $weighted = 0;

        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = $counts[$star] ?? 0;
            $total += $count;
            $weighted += $count * $star;

            $slices[] = AdminChartSliceData::count(
                __(':star star', ['star' => $star]),
                $count,
            );
        }

        return [
            'slices' => $slices,
            'average' => $total > 0 ? round($weighted / $total, 1) : null,
            'total' => $total,
        ];
    }
}

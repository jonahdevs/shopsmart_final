<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminActivityRowData;
use App\Data\AdminChartSliceData;
use App\Data\AdminDashboardChartsData;
use App\Data\AdminDashboardCountrySliceData;
use App\Data\AdminDashboardShareSliceData;
use App\Data\AdminDashboardStatsData;
use App\Data\AdminDashboardTimelineData;
use App\Data\AdminDashboardVisitorsData;
use App\Data\AdminDateRangeData;
use App\Data\AdminOrderRowData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardIndexRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Visitor;
use App\Settings\CurrencySettings;
use App\Settings\InventorySettings;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

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
 * The window itself is chosen by the reader and defaults to the last thirty
 * days. It governs every period-sensitive figure: the four headline tiles and
 * their deltas, the trading timeline, orders by status, revenue by method, the
 * best-seller ranking and both visitor breakdowns.
 *
 * Four things deliberately ignore it, because they are not period results and
 * silently windowing them would answer a question nobody asked. The three queue
 * counters are "how much work is waiting right now"; the rating spread is a
 * store's lifetime reputation, which a thin month would swing wildly. Both say
 * so on the page, because they sit among panels that do honour the window and a
 * reader would otherwise assume they had.
 *
 * The recent order and activity lists ignore it too, but say nothing: they are
 * lists of dated rows rather than aggregates, and a reader can see for
 * themselves when each one happened.
 *
 * The charts are deferred. The tiles are six cheap aggregates and should paint
 * on the first response; the six breakdowns below them each cost a grouped scan
 * over orders or order items, and holding the whole page back for them makes a
 * fast dashboard feel slow.
 */
class DashboardController extends Controller
{
    /** How many recent orders the overview lists. */
    private const RECENT_ORDERS = 8;

    /** Rows in a "top N" breakdown. Beyond this a chart stops being readable. */
    private const TOP_N = 6;

    /**
     * How many audit entries the activity panel lists. Five, because the panel
     * is a fixed-height card — its skeleton has to match it or the page jumps
     * when the prop lands — and a sixth row squeezes each one below the height
     * its two lines of text need.
     */
    private const RECENT_ACTIVITY = 5;

    /**
     * Named platforms a visitor breakdown may show before the tail is folded
     * into "Other". One below the palette's six slots, because "Other" takes
     * the last one and two segments sharing a colour is worse than a tail.
     */
    private const TOP_PLATFORMS = 5;

    public function __invoke(DashboardIndexRequest $request): Response
    {
        /*
          One window governs every period-sensitive figure on the page. A panel
          that quietly measured a different span would put two numbers on one
          screen that cannot be read against each other, and nothing on the
          screen would say so.

          The window is resolved once and handed down rather than re-derived per
          panel: `Carbon::now()` moves, and six panels each calling it would
          each get a slightly different "now".
        */
        $range = $request->range();
        $start = $range->start();
        $end = $range->end();

        $props = [
            'range' => AdminDateRangeData::fromRange($range),
            'stats' => $this->stats($range),
            'recentOrders' => $this->recentOrders(),
            'charts' => Inertia::defer(
                fn (): AdminDashboardChartsData => $this->charts($start, $end)
            ),
            'visitors' => Inertia::defer(
                fn (): AdminDashboardVisitorsData => $this->visitors($start, $end)
            ),
        ];

        $viewer = $request->user();

        /*
          The audit trail is personal data in its own right — it records staff
          actions against customer orders and payments — so the panel is not
          merely hidden from a viewer without `activity.view`: the rows are
          never assembled and never sent. Omitting the prop entirely rather than
          sending an empty list is the difference between "you may not see this"
          and "there is nothing here", and only one of those is true.
        */
        if ($viewer instanceof User && $viewer->can('activity.view')) {
            $props['activity'] = Inertia::defer(
                fn (): array => $this->recentActivity($viewer)
            );
        }

        return Inertia::render('admin/Dashboard', $props);
    }

    private function stats(DateRange $range): AdminDashboardStatsData
    {
        $before = $range->previous();

        $current = $this->paidTotals($range->start(), $range->end());
        $previous = $this->paidTotals($before->start(), $before->end());

        $paidCount = $current['count'];
        $revenue = $current['revenue'];

        $newCustomers = $this->newCustomerCount($range->start(), $range->end());
        $previousCustomers = $this->newCustomerCount($before->start(), $before->end());

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
            periodLabel: $range->label(),
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
        $methods = $this->revenueByMethod($windowStart, $now);

        return new AdminDashboardChartsData(
            timeline: $this->timeline($windowStart, $now),
            ordersByStatus: $this->ordersByStatus($windowStart, $now),
            revenueByMethod: $methods['slices'],
            revenueByMethodTotalFormatted: $methods['totalFormatted'],
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
        $averageOrderSeries = [];

        for ($day = $windowStart->copy()->startOfDay(); $day->lte($now); $day->addDay()) {
            $key = $day->toDateString();

            $dayRevenue = (int) ($revenue[$key] ?? 0);
            $dayOrders = (int) ($orders[$key] ?? 0);

            $labels[] = $day->format('j M');
            $revenueSeries[] = round($dayRevenue / 100, 2);
            $orderSeries[] = $dayOrders;
            $customerSeries[] = (int) ($customers[$key] ?? 0);
            // Averaged per day, not smeared across the window: a day that took
            // no money has no average order, and 0 is the honest bucket for it.
            $averageOrderSeries[] = $dayOrders > 0
                ? round(intdiv($dayRevenue, $dayOrders) / 100, 2)
                : 0.0;
        }

        return new AdminDashboardTimelineData(
            labels: $labels,
            revenue: $revenueSeries,
            orders: $orderSeries,
            customers: $customerSeries,
            averageOrder: $averageOrderSeries,
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
     * The order is the enum's, which is the order an order actually moves
     * through. Ranking these by size would put "Completed" first and hide the
     * fact that the list is a lifecycle, and the page draws them as a ladder
     * for that reason: the reader is looking for where work has piled up, which
     * is a position in the sequence, not a rank.
     *
     * Each row carries the enum's own badge variant so the ladder tints itself
     * from `app/Enums` exactly as the order tables do.
     *
     * @return list<AdminDashboardShareSliceData>
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

        $total = array_sum($counts);

        return array_map(
            fn (OrderStatus $status): AdminDashboardShareSliceData => AdminDashboardShareSliceData::count(
                $status->label(),
                $counts[$status->value] ?? 0,
                $total,
                $status->badgeVariant(),
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
     * Each row also carries its share of the window's captured revenue, because
     * the panel states the split twice: once as a donut, which answers "roughly
     * how is this divided" at a glance, and once as a legend listing the money,
     * which answers "how much exactly". The slice angles and the legend's
     * percentages must be the same arithmetic, so both come from here.
     *
     * The total goes back with the rows for the same reason. It is what the
     * donut prints in its hole, and it is the denominator every share was
     * divided by — computing it twice is how a hole ends up disagreeing with
     * the slices drawn around it.
     *
     * @return array{slices: list<AdminDashboardShareSliceData>, totalFormatted: string}
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

        $total = (int) $rows->sum(fn (object $row): int => (int) $row->revenue);

        return [
            'slices' => array_values($rows
                ->map(fn (object $row): AdminDashboardShareSliceData => AdminDashboardShareSliceData::money(
                    $this->methodLabel($row->payment_method),
                    (int) $row->revenue,
                    $total,
                ))
                ->all()),
            'totalFormatted' => money($total),
        ];
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
     * Counted twice in one pass, because the ladder needs both: the count is
     * what a moderator acts on ("eleven one-star reviews") and the share is
     * what a reader compares across the five rows.
     *
     * @return array{slices: list<AdminDashboardShareSliceData>, average: ?float, total: int}
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

        $stars = [5, 4, 3, 2, 1];
        $total = 0;
        $weighted = 0;

        foreach ($stars as $star) {
            $total += $counts[$star] ?? 0;
            $weighted += ($counts[$star] ?? 0) * $star;
        }

        // Totalled before the rows are built, not alongside them: every row's
        // share is a fraction of the same denominator, and a running total
        // would give the five-star row a different one from the one-star row.
        $slices = array_map(
            fn (int $star): AdminDashboardShareSliceData => AdminDashboardShareSliceData::count(
                __(':star star', ['star' => $star]),
                $counts[$star] ?? 0,
                $total,
            ),
            $stars,
        );

        return [
            'slices' => $slices,
            'average' => $total > 0 ? round($weighted / $total, 1) : null,
            'total' => $total,
        ];
    }

    /**
     * Browsing sessions in the window, split by platform and by country.
     *
     * Three queries, not four. The totals — sessions and first-time — are
     * conditional sums in a single pass, because they share one denominator and
     * reading them separately would let two scans of the same rows disagree if
     * a visit landed between them.
     *
     * `visitors` only ever holds sessions from people who granted analytics
     * consent, so an empty result is the ordinary state of a store that offers
     * no banner. Both panels say so rather than drawing an empty chart.
     */
    private function visitors(Carbon $windowStart, Carbon $now): AdminDashboardVisitorsData
    {
        $totals = Visitor::query()
            ->between($windowStart, $now)
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_new = 1 THEN 1 ELSE 0 END), 0) as first_time')
            ->first();

        $sessions = (int) ($totals?->getAttribute('sessions') ?? 0);
        $firstTime = (int) ($totals?->getAttribute('first_time') ?? 0);

        return new AdminDashboardVisitorsData(
            sessionCount: $sessions,
            newCount: $firstTime,
            returningCount: $sessions - $firstTime,
            platforms: $this->visitorPlatforms($windowStart, $now, $sessions),
            countries: $this->visitorCountries($windowStart, $now),
        );
    }

    /**
     * Sessions by country, largest first.
     *
     * Uncapped, unlike the platform breakdown: a map has as many colours as it
     * has regions, so there is no ramp to run out of and no honest way to fold
     * a tail — "Other" is not a place and cannot be shaded.
     *
     * A session the edge could not place is left out rather than counted as a
     * blank country. It is not a country with no name; it is a session whose
     * country is unknown, and the two would be indistinguishable on the map.
     * That also means the shares divide by the placed sessions, not by every
     * session in the window: the panel answers "of the visits we can locate,
     * where did they come from", and shares that quietly stopped short of a
     * hundred would read as a map that had lost regions.
     *
     * @return list<AdminDashboardCountrySliceData>
     */
    private function visitorCountries(Carbon $windowStart, Carbon $now): array
    {
        /** @var Collection<int, object{country: string, sessions: mixed}> $rows */
        $rows = Visitor::query()
            ->between($windowStart, $now)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as sessions')
            ->groupBy('country')
            ->orderByDesc('sessions')
            // Countries tie constantly on a small store, and a list that
            // reshuffles between two reloads of the same window looks like the
            // figures moved when nothing did.
            ->orderBy('country')
            ->get();

        $placed = (int) $rows->sum(fn (object $row): int => (int) $row->sessions);

        return array_values($rows
            ->map(fn (object $row): AdminDashboardCountrySliceData => AdminDashboardCountrySliceData::make(
                $row->country,
                (int) $row->sessions,
                $placed,
            ))
            ->all());
    }

    /**
     * Sessions by operating system, largest first, with the tail folded in.
     *
     * Capped here rather than in the browser for the reason every breakdown on
     * this page is: the series ramp defines six colours and does not cycle, so
     * a seventh platform would either share a hue with the first or go
     * uncoloured. Folding the remainder into one honest "Other" segment keeps
     * the shares adding to a hundred, which a truncated list would not.
     *
     * @return list<AdminDashboardShareSliceData>
     */
    private function visitorPlatforms(Carbon $windowStart, Carbon $now, int $sessions): array
    {
        /** @var Collection<int, object{platform: ?string, sessions: mixed}> $rows */
        $rows = Visitor::query()
            ->between($windowStart, $now)
            ->selectRaw('platform, COUNT(*) as sessions')
            ->groupBy('platform')
            ->orderByDesc('sessions')
            ->get();

        $platforms = array_values($rows
            ->take(self::TOP_PLATFORMS)
            ->map(fn (object $row): AdminDashboardShareSliceData => AdminDashboardShareSliceData::count(
                $this->platformLabel($row->platform),
                (int) $row->sessions,
                $sessions,
            ))
            ->all());

        $tail = (int) $rows
            ->slice(self::TOP_PLATFORMS)
            ->sum(fn (object $row): int => (int) $row->sessions);

        if ($tail > 0) {
            $platforms[] = AdminDashboardShareSliceData::count(__('Other'), $tail, $sessions);
        }

        return $platforms;
    }

    /**
     * A platform nobody could read off the user-agent string is "Unknown", not
     * dropped: it is a real share of the traffic, and hiding it would make the
     * remaining segments look bigger than they are.
     */
    private function platformLabel(?string $platform): string
    {
        return $platform === null || $platform === '' ? __('Unknown') : $platform;
    }

    /**
     * The last few things staff did, through the audit trail's own projection.
     *
     * {@see AdminActivityRowData} is reused rather than reimplemented because
     * it is what enforces the rule that `activity.view` buys you the shape of
     * an event and not automatically its contents — a viewer who cannot see
     * orders gets the attribute names and null values, and an unrecognised
     * subject type is treated as secret. A looser projection built for a
     * dashboard panel would quietly undo all of that.
     *
     * @return list<AdminActivityRowData>
     */
    private function recentActivity(User $viewer): array
    {
        $visibility = AdminActivityRowData::visibilityFor($viewer);

        return array_values(Activity::query()
            ->with(['causer', 'subject'])
            ->orderByDesc('created_at')
            // Equal timestamps are ordinary in a busy hour; without a
            // tiebreaker the six most recent rows are not a stable six.
            ->orderByDesc('id')
            ->take(self::RECENT_ACTIVITY)
            ->get()
            ->map(fn (Activity $activity): AdminActivityRowData => AdminActivityRowData::fromModel(
                $activity,
                $visibility[$activity->subject_type ?? ''] ?? false,
            ))
            ->all());
    }
}

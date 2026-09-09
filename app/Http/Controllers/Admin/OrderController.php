<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminDashboardStatsData;
use App\Data\AdminDateRangeData;
use App\Data\AdminOrderDetailData;
use App\Data\AdminOrderRowData;
use App\Data\AdminOrderStatsData;
use App\Data\PaginationData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderNoteRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's orders, as staff work them.
 *
 * Reading is separated from acting: `orders.view` gets the table and the detail
 * page, `orders.manage` is required to move an order or annotate it. Support
 * staff hold both; a read-only role can be given the first without the second.
 *
 * Every transition goes through {@see Order::changeStatus()} rather than a
 * direct write, because that method is what guards against two staff members
 * moving the same order at the same moment and sending the customer two emails.
 */
class OrderController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the orders table. */
    private const PER_PAGE = 25;

    /** The trailing window the trading tiles are measured over. */
    private const WINDOW_DAYS = 30;

    public function index(OrderIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'placed_at';
        $direction = $request->validated('direction') ?? 'desc';
        $range = $request->dateRange();

        $orders = Order::query()
            // An aggregate rather than a loaded relation: this page shows 25
            // orders, and hydrating every line of each to display "4 items"
            // would be the one query here that grows with the basket size.
            ->withSum('items', 'quantity')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request, $range))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/orders/Index', [
            'orders' => array_values(array_map(
                fn (Order $order): AdminOrderRowData => AdminOrderRowData::fromModel($order),
                $orders->items(),
            )),
            'pagination' => PaginationData::fromPaginator($orders),
            'filters' => [
                'search' => $request->validated('search'),
                'status' => $request->validated('status'),
                'payment_status' => $request->validated('payment_status'),
                'range' => $request->validated('range'),
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            /*
              The resolved window beside the raw filters: the URL says which
              preset was chosen, this says what that preset means today.
            */
            'dateRange' => AdminDateRangeData::fromRange($range),
            'statusOptions' => OrderStatus::options(),
            'paymentStatusOptions' => PaymentStatus::options(),
            'stats' => $this->stats(),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/Show', [
            'detail' => AdminOrderDetailData::fromModel($order),
        ]);
    }

    /**
     * Move the order's fulfilment status.
     *
     * Which moves are legal is settled by {@see UpdateOrderStatusRequest} before
     * this runs, so anything arriving here is either a permitted transition or a
     * no-op. A refused move is reported as a validation error rather than a
     * flash, because it means the page was showing a status the order had
     * already left — the staff member needs the form to say so, not a toast that
     * scrolls away.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = OrderStatus::from((string) $request->validated('status'));

        // The picker renders the current status as its selected option, so a
        // form submitted untouched lands here routinely. That is a no-op, not a
        // failure: it must not error and it must not email the customer.
        // Handled before changeStatus(), which reports a same-status write as
        // false and cannot tell it apart from losing a race.
        if ($order->status === $status) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This order is already :status.', ['status' => mb_strtolower($status->label())]),
            ]);

            return back();
        }

        if (! $order->changeStatus($status)) {
            return back()->withErrors([
                'status' => __('That order has already moved on. Reload to see where it is now.'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order marked :status.', ['status' => mb_strtolower($status->label())]),
        ]);

        return back();
    }

    /**
     * The internal note. Never shown to the customer — `customer_note` is
     * theirs, this one is the store's, and the two are separate columns
     * precisely so a staff remark cannot end up on a receipt.
     */
    public function updateNote(UpdateOrderNoteRequest $request, Order $order): RedirectResponse
    {
        $order->update(['staff_note' => $request->validated('staff_note')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note saved.')]);

        return back();
    }

    /**
     * The four tiles above the table, in one pass over `orders`.
     *
     * Six figures, one query. Asking for them separately would be six scans of
     * the same table to draw one row of the same screen, so the whole set is
     * expressed as conditional sums instead — the two queue counts, and the
     * current and previous window on each side of the two trading deltas.
     *
     * The queue counts are lifetime and deliberately match their tile's link
     * exactly: "awaiting fulfilment" is `processing` alone, not processing plus
     * out-for-delivery, because the tile filters the table to `processing` and
     * a count that disagreed with the list it opens is a bug staff would have
     * to discover for themselves.
     *
     * Revenue is measured on `paid_at`, not `placed_at`: an order placed in one
     * month and collected in the next is revenue for the month the money
     * arrived.
     *
     * The live window closes on `<=` and the previous one on `<`. Both matter:
     * an order collected this very second belongs to today rather than to
     * nowhere, and the two windows still meet without an order that lands on
     * the seam being counted twice.
     */
    private function stats(): AdminOrderStatsData
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);
        $previousStart = $now->copy()->subDays(self::WINDOW_DAYS * 2);

        $paid = PaymentStatus::Success->value;

        $row = Order::query()
            ->selectRaw(
                <<<'SQL'
                    SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as awaiting_payment,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as awaiting_fulfilment,
                    SUM(CASE WHEN payment_status = ? AND paid_at >= ? AND paid_at <= ? THEN 1 ELSE 0 END) as paid_orders,
                    COALESCE(SUM(CASE WHEN payment_status = ? AND paid_at >= ? AND paid_at <= ? THEN total_cents ELSE 0 END), 0) as revenue,
                    SUM(CASE WHEN payment_status = ? AND paid_at >= ? AND paid_at < ? THEN 1 ELSE 0 END) as previous_paid_orders,
                    COALESCE(SUM(CASE WHEN payment_status = ? AND paid_at >= ? AND paid_at < ? THEN total_cents ELSE 0 END), 0) as previous_revenue
                    SQL,
                [
                    PaymentStatus::Pending->value,
                    OrderStatus::Processing->value,
                    $paid, $windowStart, $now,
                    $paid, $windowStart, $now,
                    $paid, $previousStart, $windowStart,
                    $paid, $previousStart, $windowStart,
                ],
            )
            ->first();

        $paidOrders = (int) ($row?->getAttribute('paid_orders') ?? 0);
        $revenue = (int) ($row?->getAttribute('revenue') ?? 0);
        $previousPaidOrders = (int) ($row?->getAttribute('previous_paid_orders') ?? 0);
        $previousRevenue = (int) ($row?->getAttribute('previous_revenue') ?? 0);

        // Integer division on purpose: this is money, and a fractional cent has
        // nowhere to go. Guarded because a month with no sales is a perfectly
        // ordinary state for a new store.
        $averageOrderValue = $paidOrders > 0 ? intdiv($revenue, $paidOrders) : 0;
        $previousAverage = $previousPaidOrders > 0
            ? intdiv($previousRevenue, $previousPaidOrders)
            : 0;

        return new AdminOrderStatsData(
            awaitingPaymentCount: (int) ($row?->getAttribute('awaiting_payment') ?? 0),
            awaitingFulfilmentCount: (int) ($row?->getAttribute('awaiting_fulfilment') ?? 0),
            revenueCents: $revenue,
            revenueFormatted: money($revenue),
            // The overview's helper rather than a second one: what a delta means
            // — and that a first period of trading shows none — is settled in
            // one place for every tile in the back office.
            revenueChangePercent: AdminDashboardStatsData::changePercent($revenue, $previousRevenue),
            averageOrderValueCents: $averageOrderValue,
            averageOrderValueFormatted: money($averageOrderValue),
            averageOrderValueChangePercent: AdminDashboardStatsData::changePercent($averageOrderValue, $previousAverage),
            periodLabel: __('Last :days days', ['days' => self::WINDOW_DAYS]),
        );
    }

    /**
     * Narrow the table by the filter bar.
     *
     * @param  Builder<Order>  $query
     */
    private function applyFilters(Builder $query, OrderIndexRequest $request, ?DateRange $range): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('order_number'), [$pattern])
                    ->orWhereRaw($this->likeExpression('customer_name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('customer_email'), [$pattern]);
            });
        }

        $query
            ->when($request->validated('status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->validated('payment_status'), fn (Builder $q, string $status) => $q->where('payment_status', $status))
            /*
              One `whereBetween` over resolved instants rather than a pair of
              `whereDate` comparisons, which wrap the column in a function the
              index cannot be used through. The window is already inclusive of
              both its end days.
            */
            ->when($range, fn (Builder $q, DateRange $window) => $q
                ->whereBetween('placed_at', [$window->start(), $window->end()]));
    }
}

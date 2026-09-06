<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminOrderDetailData;
use App\Data\AdminOrderRowData;
use App\Data\PaginationData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderNoteRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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

    public function index(OrderIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'placed_at';
        $direction = $request->validated('direction') ?? 'desc';

        $orders = Order::query()
            // An aggregate rather than a loaded relation: this page shows 25
            // orders, and hydrating every line of each to display "4 items"
            // would be the one query here that grows with the basket size.
            ->withSum('items', 'quantity')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
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
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => OrderStatus::options(),
            'paymentStatusOptions' => PaymentStatus::options(),
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
     * A refused move is reported as a validation error rather than a flash,
     * because it means the page was showing a status the order had already left
     * — the staff member needs the form to say so, not a toast that scrolls
     * away.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = OrderStatus::from((string) $request->validated('status'));

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
     * Narrow the table by the filter bar.
     *
     * @param  Builder<Order>  $query
     */
    private function applyFilters(Builder $query, OrderIndexRequest $request): void
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
            ->when($request->validated('from'), fn (Builder $q, string $from) => $q->whereDate('placed_at', '>=', $from))
            ->when($request->validated('to'), fn (Builder $q, string $to) => $q->whereDate('placed_at', '<=', $to));
    }
}

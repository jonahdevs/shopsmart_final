<?php

namespace App\Http\Controllers\Shop;

use App\Data\BreadcrumbData;
use App\Data\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Settings\ShippingSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's own orders: the confirmation they land on after checking out,
 * and the history they come back to later.
 *
 * Ownership is checked with a 404 rather than a 403 — telling a stranger that
 * an order number exists but is not theirs is more than they need to know.
 * Phase 6 grows the surrounding account area; phase 7 adds the staff view,
 * which is when a policy will start to earn its keep.
 */
class OrderController extends Controller
{
    /** How many orders one page of history holds. */
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with('items')
            ->forCustomer((int) $request->user()?->getKey())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('shop/Orders', [
            'orders' => array_values(array_map(
                fn (Order $order): OrderData => OrderData::fromModel($order),
                $orders->items(),
            )),
            'hasMore' => $orders->hasMorePages(),
            'breadcrumbs' => $this->breadcrumbs(__('Your orders')),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()?->getKey(), 404);

        $order->load('items');

        return Inertia::render('shop/Order', [
            'order' => OrderData::fromModel($order),
            'pickupAddress' => app(ShippingSettings::class)->pickup_address,
            'breadcrumbs' => $this->breadcrumbs($order->order_number),
        ]);
    }

    /**
     * Home / this page.
     *
     * Kept to two rungs because StoreBreadcrumbs reads a null slug past the
     * first rung as the Categories root, so an intermediate "Orders" rung would
     * link somewhere it should not.
     *
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(string $current): array
    {
        return [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: $current, slug: null),
        ];
    }
}

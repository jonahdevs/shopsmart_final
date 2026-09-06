<?php

namespace App\Data;

use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One customer as staff read them: who they are, what they bought, where it
 * went, and what they said about it.
 *
 * This is the page in the admin panel that shows the most real personal data
 * about a living person, so what it deliberately does NOT carry is part of the
 * contract:
 *
 *  - no `payments.payload` — the gateway response is encrypted at rest because
 *    it holds the payer's name, phone and masked instrument, and none of that
 *    is needed to answer "did this order get paid";
 *  - no password, two-factor secret, recovery codes, remember token or passkey;
 *  - nothing reconstructed from orders whose `user_id` is null. A closed
 *    account leaves its orders standing under their frozen `customer_name` and
 *    `customer_email`, and matching those back to a person would undo the
 *    deletion the customer asked for.
 *
 * `lifetimeSpentCents` counts paid orders only — see
 * {@see PaymentStatus::Success}. Money the store never collected is not spend.
 */
#[TypeScript]
class AdminCustomerDetailData extends Data
{
    /**
     * @param  list<AdminOrderRowData>  $orders
     * @param  list<AddressData>  $addresses
     * @param  list<AdminReviewRowData>  $reviews
     */
    public function __construct(
        public AdminCustomerRowData $customer,
        public array $orders,
        public array $addresses,
        public array $reviews,
        public int $paidOrderCount,
        public int $averageOrderValueCents,
        public string $averageOrderValueFormatted,
        public int $reviewCount,
    ) {}

    public static function fromModel(User $customer): self
    {
        $orders = $customer->orders()
            ->withSum('items', 'quantity')
            ->orderByDesc('placed_at')
            ->get();

        $addresses = $customer->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $reviews = Review::query()
            ->with('product:id,name,slug')
            ->where('user_id', $customer->getKey())
            ->orderByDesc('created_at')
            ->get();

        $paid = $orders->filter(
            fn (Order $order): bool => $order->payment_status === PaymentStatus::Success,
        );

        $spent = (int) $paid->sum('total_cents');
        $paidCount = $paid->count();

        // Integer division on purpose: this is money, and a fractional cent has
        // nowhere to go.
        $average = $paidCount > 0 ? intdiv($spent, $paidCount) : 0;

        $customer->setAttribute('orders_count', $orders->count());
        $customer->setAttribute('lifetime_spent_cents', $spent);
        $customer->setAttribute('last_order_at', $orders->first()?->placed_at?->toDateTimeString());

        return new self(
            customer: AdminCustomerRowData::fromModel($customer),
            orders: array_values($orders
                ->map(fn (Order $order): AdminOrderRowData => AdminOrderRowData::fromModel($order))
                ->all()),
            addresses: array_values($addresses
                ->map(fn (Address $address): AddressData => AddressData::fromModel($address))
                ->all()),
            reviews: array_values($reviews
                ->map(fn (Review $review): AdminReviewRowData => AdminReviewRowData::fromModel($review))
                ->all()),
            paidOrderCount: $paidCount,
            averageOrderValueCents: $average,
            averageOrderValueFormatted: money($average),
            reviewCount: $reviews->count(),
        );
    }
}

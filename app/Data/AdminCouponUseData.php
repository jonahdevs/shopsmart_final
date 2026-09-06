<?php

namespace App\Data;

use App\Models\CouponUse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One redemption of a coupon, for the history panel on the coupon page.
 *
 * The customer is named from the order's frozen `customer_name`, not from the
 * account: a redemption that happened is a fact about an order, and it stays
 * readable after the shopper closes their account and `user_id` goes null.
 */
#[TypeScript]
class AdminCouponUseData extends Data
{
    public function __construct(
        public int $id,
        /** Null only if the order behind the redemption has been deleted. */
        public ?string $orderNumber,
        public string $customerName,
        /** Null once the customer has deleted their account. */
        public ?int $customerId,
        public int $discountCents,
        public string $discountFormatted,
        public string $redeemedAt,
    ) {}

    public static function fromModel(CouponUse $use): self
    {
        $order = $use->order;

        return new self(
            id: $use->getKey(),
            orderNumber: $order?->order_number,
            customerName: $order === null ? __('Unknown') : $order->customer_name,
            customerId: $use->user_id,
            discountCents: $use->discount_cents,
            discountFormatted: money($use->discount_cents),
            redeemedAt: $use->created_at?->toIso8601String() ?? '',
        );
    }
}

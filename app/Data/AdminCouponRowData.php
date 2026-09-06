<?php

namespace App\Data;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One coupon, as the table lists it and the edit form prefills from it.
 *
 * Money makes three trips rather than the usual two. Integer cents so the table
 * can compare, a `money()` string so the client never formats currency, and a
 * major-unit float for the form — because the edit form is the one screen where
 * a staff member types whole KES back in, and prefilling it from cents would
 * mean the client dividing by 100, which is exactly what
 * {@see Money::toMajor()} exists to stop.
 *
 * `usedCount` is the coupon's own counter and `redemptionCount` counts the
 * `coupon_uses` rows behind it. They are shown side by side deliberately: the
 * two are kept in step by {@see Order::recordCouponUse()} and a
 * disagreement between them is a bug staff should be able to see.
 */
#[TypeScript]
class AdminCouponRowData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public CouponType $type,
        public string $typeLabel,
        /** The discount as one readable string: "KES 500" or "10%". */
        public string $valueLabel,
        /** Null for a percentage coupon. */
        public ?int $amountCents,
        public ?float $amountMajor,
        /** Null for a fixed-amount coupon. */
        public ?float $percent,
        public int $minSubtotalCents,
        public float $minSubtotalMajor,
        public string $minSubtotalFormatted,
        /** The ceiling on a percentage discount; null means uncapped. */
        public ?int $maxDiscountCents,
        public ?float $maxDiscountMajor,
        public ?string $maxDiscountFormatted,
        /** Null on both counters means unlimited. */
        public ?int $usageLimit,
        public ?int $usageLimitPerUser,
        public int $usedCount,
        public int $redemptionCount,
        public ?string $startsAt,
        public ?string $expiresAt,
        public bool $isActive,
        /** True while the coupon is switched on and inside its date window. */
        public bool $isRedeemable,
        public ?string $description,
    ) {}

    public static function fromModel(Coupon $coupon): self
    {
        $money = app(Money::class);

        return new self(
            id: $coupon->getKey(),
            code: $coupon->code,
            type: $coupon->type,
            typeLabel: $coupon->type->label(),
            valueLabel: self::describeValue($coupon),
            amountCents: $coupon->amount_cents,
            amountMajor: $coupon->amount_cents === null ? null : $money->toMajor($coupon->amount_cents),
            percent: $coupon->percent === null ? null : (float) $coupon->percent,
            minSubtotalCents: $coupon->min_subtotal_cents,
            minSubtotalMajor: $money->toMajor($coupon->min_subtotal_cents),
            minSubtotalFormatted: money($coupon->min_subtotal_cents),
            maxDiscountCents: $coupon->max_discount_cents,
            maxDiscountMajor: $coupon->max_discount_cents === null ? null : $money->toMajor($coupon->max_discount_cents),
            maxDiscountFormatted: $coupon->max_discount_cents === null ? null : money($coupon->max_discount_cents),
            usageLimit: $coupon->usage_limit,
            usageLimitPerUser: $coupon->usage_limit_per_user,
            usedCount: $coupon->used_count,
            // Set by `withCount('uses')` in CouponController; falls back to the
            // counter when a caller hands over a plain model.
            redemptionCount: (int) ($coupon->getAttribute('uses_count') ?? $coupon->used_count),
            startsAt: $coupon->starts_at?->toIso8601String(),
            expiresAt: $coupon->expires_at?->toIso8601String(),
            isActive: $coupon->is_active,
            isRedeemable: $coupon->validateFor(null, $coupon->min_subtotal_cents) === null,
            description: $coupon->description,
        );
    }

    /**
     * The discount in the shape staff read it in a table cell.
     */
    private static function describeValue(Coupon $coupon): string
    {
        return match ($coupon->type) {
            CouponType::Fixed => money($coupon->amount_cents ?? 0),
            CouponType::Percent => rtrim(rtrim(number_format((float) $coupon->percent, 2), '0'), '.').'%',
        };
    }
}

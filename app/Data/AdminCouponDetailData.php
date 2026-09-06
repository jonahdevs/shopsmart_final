<?php

namespace App\Data;

use App\Models\Coupon;
use App\Models\CouponUse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One coupon with the redemptions it has actually taken.
 *
 * `discountedTotalCents` is the money this code has given away, summed from the
 * `coupon_uses` rows rather than from the coupon's own `used_count` — the
 * counter says how many times, the rows say how much.
 */
#[TypeScript]
class AdminCouponDetailData extends Data
{
    /**
     * @param  list<AdminCouponUseData>  $redemptions
     */
    public function __construct(
        public AdminCouponRowData $coupon,
        public array $redemptions,
        public int $discountedTotalCents,
        public string $discountedTotalFormatted,
    ) {}

    public static function fromModel(Coupon $coupon): self
    {
        $coupon->loadMissing(['uses.order']);
        $coupon->loadCount('uses');

        $redemptions = array_values($coupon->uses
            ->sortByDesc('created_at')
            ->map(fn (CouponUse $use): AdminCouponUseData => AdminCouponUseData::fromModel($use))
            ->all());

        $total = (int) $coupon->uses->sum('discount_cents');

        return new self(
            coupon: AdminCouponRowData::fromModel($coupon),
            redemptions: $redemptions,
            discountedTotalCents: $total,
            discountedTotalFormatted: money($total),
        );
    }
}

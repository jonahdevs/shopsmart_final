<?php

namespace App\Http\Requests\Admin;

/**
 * An edit to an existing discount code.
 *
 * Identical to {@see StoreCouponRequest} — including the deliberate absence of
 * `used_count` — save for the unique rule, which already ignores the bound
 * `{coupon}` there. Kept as its own class so the route reads as an edit and so
 * the two can diverge without one silently changing the other.
 */
class UpdateCouponRequest extends StoreCouponRequest {}

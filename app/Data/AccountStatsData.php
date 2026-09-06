<?php

namespace App\Data;

use App\Support\StorefrontSession;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The counters across the top of the account dashboard.
 *
 * Every number here is a COUNT, never a hydrated collection: the dashboard
 * shows three orders and a rail, so loading a shopper's whole history just to
 * call `count()` on it would be the one query on this page that grows without
 * bound.
 *
 * `wishlistCount` comes out of the session rather than the database, because
 * {@see StorefrontSession} keeps the session as the live copy of
 * the wishlist for signed-in customers too.
 */
#[TypeScript]
class AccountStatsData extends Data
{
    public function __construct(
        public int $orderCount,
        public int $addressCount,
        public int $wishlistCount,
        /** Products bought and delivered that the shopper has not reviewed yet. */
        public int $awaitingReviewCount,
    ) {}
}

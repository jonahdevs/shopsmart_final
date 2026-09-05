<?php

namespace App\Data;

use App\Support\StorefrontSession;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The current shopper's cart, wishlist and compare state, shared on every
 * storefront response.
 *
 * This is read entirely out of the session — for guests and for signed-in
 * customers alike, because the session always holds the live copy — so sharing
 * it costs no query on a path that runs on every single request.
 *
 * The id lists travel alongside the counts so a product tile can render its own
 * "saved" and "comparing" state without the page having to fetch it. They are
 * bounded: compare is capped at {@see StorefrontSession::COMPARE_LIMIT},
 * and a wishlist is a hand-curated list of a few dozen at most.
 */
#[TypeScript]
class ShopperStateData extends Data
{
    /**
     * @param  list<int>  $wishlistProductIds
     * @param  list<int>  $compareProductIds
     */
    public function __construct(
        /** Total units in the cart, not distinct lines. */
        public int $cartCount,
        public int $wishlistCount,
        public int $compareCount,
        public array $wishlistProductIds,
        public array $compareProductIds,
        public int $compareLimit,
    ) {}
}

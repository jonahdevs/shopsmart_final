<?php

namespace App\Enums;

use App\Support\StorefrontSession;

/**
 * The two saved-product lists a shopper keeps alongside their cart.
 *
 * A wishlist is open-ended and long-lived; a compare tray is a browsing aid
 * capped at {@see StorefrontSession::COMPARE_LIMIT} entries.
 */
enum SavedProductList: string
{
    case Wishlist = 'wishlist';

    case Compare = 'compare';

    public function label(): string
    {
        return match ($this) {
            self::Wishlist => __('Wishlist'),
            self::Compare => __('Compare'),
        };
    }
}

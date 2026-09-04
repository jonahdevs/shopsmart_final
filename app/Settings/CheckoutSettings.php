<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Rules the checkout enforces: the minimum spend, how order numbers are
 * prefixed, whether guests may order, and where the terms live.
 */
class CheckoutSettings extends Settings
{
    /**
     * Minimum order value in whole KES.
     */
    public int $min_order_value;

    public string $order_prefix;

    public bool $guest_checkout_enabled;

    public string $terms_url;

    public static function group(): string
    {
        return 'checkout';
    }
}

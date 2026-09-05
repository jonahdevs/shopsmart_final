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
     * Minimum order subtotal, in cents, below which an order cannot be placed.
     * Zero disables the floor.
     */
    public int $min_order_value_cents;

    public string $order_prefix;

    public bool $guest_checkout_enabled;

    public string $terms_url;

    public static function group(): string
    {
        return 'checkout';
    }
}

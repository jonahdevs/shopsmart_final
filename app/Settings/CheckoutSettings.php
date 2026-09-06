<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Rules the checkout enforces: the minimum spend, how order numbers are
 * prefixed and whether guests may order.
 *
 * The terms URL used to live here and now sits on {@see LegalSettings} beside
 * the privacy policy it is always shown next to.
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

    public static function group(): string
    {
        return 'checkout';
    }
}

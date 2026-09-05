<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Fulfilment options: local pickup availability and address, plus the flat
 * delivery rate and the single authoritative subtotal at which it is waived.
 */
class ShippingSettings extends Settings
{
    public bool $local_pickup_enabled;

    public string $pickup_address;

    /**
     * Charged on every delivery order, in cents, unless the order has reached
     * the free-shipping threshold. Pickup is always free.
     */
    public int $flat_rate_cents;

    /**
     * Order subtotal, in cents, at or above which shipping is free. Measured
     * after any coupon, because that is what the shopper actually pays.
     */
    public int $free_shipping_threshold_cents;

    public static function group(): string
    {
        return 'shipping';
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Fulfilment options: local pickup availability and address, plus the single
 * authoritative order subtotal at which shipping becomes free.
 */
class ShippingSettings extends Settings
{
    public bool $local_pickup_enabled;

    public string $pickup_address;

    /**
     * Order subtotal, in cents, at or above which shipping is free.
     */
    public int $free_shipping_threshold_cents;

    public static function group(): string
    {
        return 'shipping';
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Stock defaults applied to new products and how the storefront reacts when an
 * item runs out.
 */
class InventorySettings extends Settings
{
    public bool $track_stock_by_default;

    public int $low_stock_threshold;

    /**
     * One of "hide", "show" or "show_unavailable".
     */
    public string $out_of_stock_behavior;

    public bool $allow_backorders_by_default;

    public static function group(): string
    {
        return 'inventory';
    }
}

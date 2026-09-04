<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * VAT behaviour: whether tax is calculated at all, the fallback tax class for
 * products without one, and whether catalogue prices already include tax.
 */
class TaxSettings extends Settings
{
    public bool $tax_enabled;

    public ?int $default_tax_class_id;

    public bool $prices_include_tax;

    public static function group(): string
    {
        return 'tax';
    }
}

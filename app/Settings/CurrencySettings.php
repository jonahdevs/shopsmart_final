<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * How monetary amounts are rendered: the symbol, where it sits relative to the
 * number, and the separators used when formatting.
 */
class CurrencySettings extends Settings
{
    public string $symbol;

    /**
     * Either "before" or "after" the amount.
     */
    public string $symbol_position;

    public int $decimals;

    public string $thousand_separator;

    public string $decimal_separator;

    public static function group(): string
    {
        return 'currency';
    }
}

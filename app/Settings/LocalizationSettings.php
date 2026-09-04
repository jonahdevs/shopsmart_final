<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Regional defaults: the trading currency, the units products are measured in,
 * and the timezone dates are presented in.
 */
class LocalizationSettings extends Settings
{
    public string $currency;

    public string $weight_unit;

    public string $dimension_unit;

    public string $timezone;

    public static function group(): string
    {
        return 'localization';
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Third-party measurement tag IDs injected into the storefront when set.
 */
class AnalyticsSettings extends Settings
{
    public string $ga4_id;

    public string $gtm_id;

    public string $meta_pixel_id;

    public static function group(): string
    {
        return 'analytics';
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Compliance toggles. Policy copy itself lives in CMS pages; this group only
 * controls the cookie consent banner.
 */
class LegalSettings extends Settings
{
    public bool $cookie_consent_enabled;

    public static function group(): string
    {
        return 'legal';
    }
}

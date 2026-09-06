<?php

namespace App\Settings;

use App\Enums\ConsentCategory;
use App\Support\AnalyticsTags;
use Spatie\LaravelSettings\Settings;

/**
 * Third-party measurement tag IDs.
 *
 * Setting one is a necessary but not a sufficient condition for the tag to
 * load: each id belongs to a {@see ConsentCategory}, and
 * {@see AnalyticsTags} only ever hands the page an id whose
 * category the store offers *and* this visitor has granted. Google measurement
 * is analytics; the Meta pixel is marketing.
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

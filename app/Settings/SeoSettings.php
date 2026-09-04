<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Search engine presentation: title and description fallbacks, and whether the
 * site invites indexing and publishes a sitemap.
 */
class SeoSettings extends Settings
{
    public string $meta_title_pattern;

    public string $default_meta_description;

    public bool $index_site;

    public bool $generate_sitemap;

    public static function group(): string
    {
        return 'seo';
    }
}

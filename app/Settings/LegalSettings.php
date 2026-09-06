<?php

namespace App\Settings;

use App\Enums\ConsentCategory;
use Spatie\LaravelSettings\Settings;

/**
 * Privacy and compliance. Policy *copy* lives in CMS pages; this group holds
 * the machinery: which consent categories the banner offers, where the policies
 * that explain them live, and how long the two personal-data trails the store
 * keeps are allowed to survive.
 *
 * `consent_categories` is the gate on every third-party tag. A category that is
 * not listed here can never be granted, which means {@see AnalyticsSettings}
 * ids belonging to it are never rendered into a page — filling one in does not
 * silently start tracking. An empty list therefore switches the banner off *and*
 * every optional tag with it, which is the safe reading of "no banner".
 */
class LegalSettings extends Settings
{
    /**
     * The optional {@see ConsentCategory} values the banner asks about.
     * `necessary` is implicit and never appears here.
     *
     * @var list<string>
     */
    public array $consent_categories;

    public string $privacy_policy_url;

    public string $terms_url;

    /**
     * Days a `recently_viewed` row is kept after it was last touched. Zero
     * keeps them indefinitely.
     */
    public int $recently_viewed_retention_days;

    /**
     * Days an activity-log entry is kept. Zero keeps them indefinitely.
     */
    public int $activity_log_retention_days;

    public static function group(): string
    {
        return 'legal';
    }

    /**
     * The categories the store actually offers, as enum cases, with anything
     * unrecognised or non-optional discarded.
     *
     * @return list<ConsentCategory>
     */
    public function offeredCategories(): array
    {
        $offered = array_filter(array_map(
            static fn (string $value): ?ConsentCategory => ConsentCategory::tryFrom($value),
            $this->consent_categories,
        ));

        return array_values(array_filter(
            $offered,
            static fn (ConsentCategory $category): bool => $category->isOptional(),
        ));
    }
}

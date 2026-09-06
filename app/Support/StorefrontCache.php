<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The storefront's cached read models, and the one place that clears them.
 *
 * Both entries are derived data with a TTL, so a missed invalidation is stale
 * rather than wrong. Naming the keys here keeps the writer and the reader from
 * drifting apart, which is how the navigation cache ended up documented as
 * being invalidated by an admin that had not been built yet.
 */
class StorefrontCache
{
    /** The curated category list shared on every storefront response. */
    public const NAV_CATEGORIES = 'storefront.nav-categories';

    /**
     * The footer's social profile links, read off SocialSettings.
     *
     * Cached for the same reason as the nav: it is shared on every response and
     * settings caching is off by default, so resolving the settings object
     * inline would put a query on every single request.
     *
     * Unlike the nav, nothing invalidates this — settings have no observer — so
     * an edit takes up to an hour to appear. The admin settings screen must
     * forget this key when it saves the social group.
     */
    public const SOCIAL_LINKS = 'storefront.social-links';

    /**
     * Live catalog product ids per category, behind every facet.
     *
     * The `.v2` suffix retires entries written when this held a tally per
     * category rather than an id list: subtree facets roll several categories
     * up, and summing tallies double-counted a product filed in two of them.
     * Version the key on any further shape change — a deploy must never read
     * one shape as another.
     */
    public const CATEGORY_PRODUCT_IDS = 'storefront.category-product-ids.v2';

    /**
     * The consent categories the banner offers, the policy URLs, and the
     * measurement tag ids those categories gate. See {@see PrivacyConfig}.
     *
     * Cached for the same reason as the social links, only more so: the privacy
     * scripts are rendered into the head of EVERY document, storefront and
     * admin alike, so resolving two settings groups inline put two queries on
     * every single page load. Nothing observes a settings save, so
     * {@see forgetPrivacy()} is called by the admin screen that writes them —
     * and by any test that writes them behind the controller's back.
     */
    public const PRIVACY = 'storefront.privacy';

    /**
     * The title pattern, description fallback, indexing switches and the
     * Organization block, read off four settings groups. See {@see Seo}.
     *
     * Cached for the same reason as the privacy config, and just as urgently:
     * the document head is built on EVERY page response, so resolving those
     * four groups inline added three queries to every storefront page and
     * broke the standing query budgets on the catalog, category and product
     * pages. Nothing observes a settings save, so {@see forgetSeo()} is called
     * by the admin screens that write them — and by any test that writes them
     * behind the controller's back.
     */
    public const SEO = 'storefront.seo';

    /**
     * The Organization block, read off BusinessSettings and SocialSettings.
     *
     * A separate key from {@see SEO} on purpose: only the home page publishes
     * it — it describes the business, not the page — and keeping it out of the
     * per-page config means every other response avoids resolving those two
     * groups at all.
     */
    public const SEO_ORGANIZATION = 'storefront.seo-organization';

    public function forgetNavigation(): void
    {
        Cache::forget(self::NAV_CATEGORIES);
    }

    public static function forgetPrivacy(): void
    {
        Cache::forget(self::PRIVACY);
    }

    public static function forgetSeo(): void
    {
        Cache::forget(self::SEO);
        Cache::forget(self::SEO_ORGANIZATION);
    }

    public function forgetCategoryCounts(): void
    {
        Cache::forget(self::CATEGORY_PRODUCT_IDS);
    }
}

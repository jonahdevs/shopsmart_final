<?php

namespace App\Support;

use App\Enums\ConsentCategory;
use App\Settings\AnalyticsSettings;
use App\Settings\LegalSettings;
use App\View\Components\PrivacyScripts;
use Illuminate\Support\Facades\Cache;

/**
 * The store's privacy configuration as a cached read model.
 *
 * {@see PrivacyScripts} runs on every document, so reading
 * {@see LegalSettings} and {@see AnalyticsSettings} inline cost two queries per
 * page load. This is the same trade the footer's social links already make, and
 * the storefront's query budgets are what caught it.
 *
 * Everything here is store-wide. Nothing about a particular visitor is cached —
 * {@see Consent} applies their cookie on top of this, per request.
 */
class PrivacyConfig
{
    /**
     * @return array{categories: list<string>, privacyPolicyUrl: string, termsUrl: string, tags: array{ga4: string, gtm: string, metaPixel: string}}
     */
    public function get(): array
    {
        $cached = Cache::get(StorefrontCache::PRIVACY);

        // Deliberately not `Cache::remember`: an entry written by an earlier
        // deploy can be in a shape this code no longer understands, and these
        // values decide whether a third-party tag loads. Anything that does not
        // read back cleanly is rebuilt and rewritten rather than trusted.
        if ($this->isWellFormed($cached)) {
            return $cached;
        }

        $fresh = $this->build();

        Cache::put(StorefrontCache::PRIVACY, $fresh, now()->addHour());

        return $fresh;
    }

    /**
     * @return array{categories: list<string>, privacyPolicyUrl: string, termsUrl: string, tags: array{ga4: string, gtm: string, metaPixel: string}}
     */
    private function build(): array
    {
        $legal = app(LegalSettings::class);
        $analytics = app(AnalyticsSettings::class);

        return [
            'categories' => array_map(
                static fn (ConsentCategory $category): string => $category->value,
                $legal->offeredCategories(),
            ),
            'privacyPolicyUrl' => $legal->privacy_policy_url,
            'termsUrl' => $legal->terms_url,
            'tags' => [
                'ga4' => trim($analytics->ga4_id),
                'gtm' => trim($analytics->gtm_id),
                'metaPixel' => trim($analytics->meta_pixel_id),
            ],
        ];
    }

    /**
     * @phpstan-assert-if-true array{categories: list<string>, privacyPolicyUrl: string, termsUrl: string, tags: array{ga4: string, gtm: string, metaPixel: string}} $cached
     */
    private function isWellFormed(mixed $cached): bool
    {
        if (! is_array($cached)) {
            return false;
        }

        foreach (['categories', 'privacyPolicyUrl', 'termsUrl', 'tags'] as $key) {
            if (! array_key_exists($key, $cached)) {
                return false;
            }
        }

        if (! is_array($cached['categories']) || ! is_array($cached['tags'])) {
            return false;
        }

        foreach (['ga4', 'gtm', 'metaPixel'] as $vendor) {
            if (! isset($cached['tags'][$vendor]) || ! is_string($cached['tags'][$vendor])) {
                return false;
            }
        }

        return is_string($cached['privacyPolicyUrl'])
            && is_string($cached['termsUrl'])
            && $cached['categories'] === array_values(array_filter($cached['categories'], 'is_string'));
    }
}

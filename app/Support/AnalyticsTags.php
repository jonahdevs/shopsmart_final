<?php

namespace App\Support;

use App\Enums\ConsentCategory;
use Illuminate\Http\Request;

/**
 * Decides which measurement tags a given response is allowed to carry.
 *
 * Every id is paired with the {@see ConsentCategory} it belongs to, and an id
 * only survives this class when the store offers that category *and* the
 * visitor granted it. The view renders what comes back and asks no questions,
 * so there is exactly one place where "may this tracker load" is answered.
 *
 * Google Tag Manager sits under analytics because that is the weakest claim it
 * can be loaded on; the container is additionally handed a Consent Mode v2
 * default, so `ad_storage` and `ad_user_data` stay denied inside it until
 * marketing has been granted too.
 */
class AnalyticsTags
{
    public function __construct(
        private PrivacyConfig $config,
        private Consent $consent,
    ) {}

    /**
     * The tag ids this request may render, keyed by vendor. A key is absent
     * when the id is blank or its category has not been granted.
     *
     * @return array{ga4?: string, gtm?: string, metaPixel?: string}
     */
    public function forRequest(Request $request): array
    {
        $tags = $this->config->get()['tags'];

        $candidates = [
            'ga4' => [$tags['ga4'], ConsentCategory::Analytics],
            'gtm' => [$tags['gtm'], ConsentCategory::Analytics],
            'metaPixel' => [$tags['metaPixel'], ConsentCategory::Marketing],
        ];

        $allowed = [];

        foreach ($candidates as $vendor => [$id, $category]) {
            if ($id !== '' && $this->consent->allows($request, $category)) {
                $allowed[$vendor] = $id;
            }
        }

        return $allowed;
    }

    /**
     * Google Consent Mode v2 signals for this request, so a container that is
     * allowed to load still knows what it may not do once inside.
     *
     * @return array<string, string>
     */
    public function googleConsentState(Request $request): array
    {
        $analytics = $this->consent->allows($request, ConsentCategory::Analytics) ? 'granted' : 'denied';
        $marketing = $this->consent->allows($request, ConsentCategory::Marketing) ? 'granted' : 'denied';

        return [
            'analytics_storage' => $analytics,
            'ad_storage' => $marketing,
            'ad_user_data' => $marketing,
            'ad_personalization' => $marketing,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\ConsentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdatePrivacySettingsRequest;
use App\Settings\AnalyticsSettings;
use App\Settings\LegalSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consent, the policies behind it, retention windows, and the measurement tags
 * the whole thing gates.
 *
 * Legal and analytics share one screen on purpose. The consent categories ARE
 * the switch on the tag ids: filling in a GA4 id while the analytics category
 * is not offered gives you a tag that can never load, and that is only obvious
 * when both are in front of you. `tagCategories` tells the page which category
 * governs which id so it can say so.
 */
class PrivacySettingsController extends Controller
{
    public function __construct(
        private LegalSettings $legal,
        private AnalyticsSettings $analytics,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Privacy', [
            'legal' => [
                'consent_categories' => $this->legal->consent_categories,
                'privacy_policy_url' => $this->legal->privacy_policy_url,
                'terms_url' => $this->legal->terms_url,
                'recently_viewed_retention_days' => $this->legal->recently_viewed_retention_days,
                'activity_log_retention_days' => $this->legal->activity_log_retention_days,
            ],
            'analytics' => [
                'ga4_id' => $this->analytics->ga4_id,
                'gtm_id' => $this->analytics->gtm_id,
                'meta_pixel_id' => $this->analytics->meta_pixel_id,
            ],
            'consentCategories' => ConsentCategory::optionalOptions(),
            'tagCategories' => [
                'ga4_id' => ConsentCategory::Analytics->value,
                'gtm_id' => ConsentCategory::Analytics->value,
                'meta_pixel_id' => ConsentCategory::Marketing->value,
            ],
        ]);
    }

    public function update(UpdatePrivacySettingsRequest $request): RedirectResponse
    {
        $this->legal->fill($request->legalValues())->save();
        $this->analytics->fill($request->analyticsValues())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Privacy settings saved.')]);

        return to_route('admin.settings.privacy');
    }
}

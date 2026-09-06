<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateBrandingSettingsRequest;
use App\Settings\BrandingSettings;
use App\Settings\SocialSettings;
use App\Support\StorefrontCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's public identity: what it is called, and where else it can be
 * found.
 *
 * Saving forgets the cached social links. HandleInertiaRequests caches them for
 * an hour and shares them on every response, so without this a staff member
 * would fix a broken profile URL and watch the footer keep the old one.
 */
class BrandingSettingsController extends Controller
{
    public function __construct(
        private BrandingSettings $branding,
        private SocialSettings $social,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Branding', [
            'branding' => [
                'store_name' => $this->branding->store_name,
                'tagline' => $this->branding->tagline,
                'logo_path' => $this->branding->logo_path,
                'favicon_path' => $this->branding->favicon_path,
            ],
            'social' => [
                'og_image_path' => $this->social->og_image_path,
                'twitter_handle' => $this->social->twitter_handle,
                'facebook_url' => $this->social->facebook_url,
                'instagram_url' => $this->social->instagram_url,
                'x_url' => $this->social->x_url,
                'linkedin_url' => $this->social->linkedin_url,
                'youtube_url' => $this->social->youtube_url,
                'whatsapp_number' => $this->social->whatsapp_number,
                'whatsapp_order_enabled' => $this->social->whatsapp_order_enabled,
            ],
        ]);
    }

    public function update(UpdateBrandingSettingsRequest $request): RedirectResponse
    {
        $this->branding->fill($request->brandingValues())->save();
        $this->social->fill($request->socialValues())->save();

        Cache::forget(StorefrontCache::SOCIAL_LINKS);
        // The store name and the social profiles both feed the Organization
        // block in every page's structured data.
        StorefrontCache::forgetSeo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding settings saved.')]);

        return to_route('admin.settings.branding');
    }
}

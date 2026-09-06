<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSeoSettingsRequest;
use App\Settings\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How the store presents itself to search engines.
 */
class SeoSettingsController extends Controller
{
    public function __construct(private SeoSettings $seo) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Seo', [
            'seo' => [
                'meta_title_pattern' => $this->seo->meta_title_pattern,
                'default_meta_description' => $this->seo->default_meta_description,
                'index_site' => $this->seo->index_site,
                'generate_sitemap' => $this->seo->generate_sitemap,
            ],
        ]);
    }

    public function update(UpdateSeoSettingsRequest $request): RedirectResponse
    {
        $this->seo->fill($request->seoValues())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SEO settings saved.')]);

        return to_route('admin.settings.seo');
    }
}

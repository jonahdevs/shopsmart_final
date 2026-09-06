<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\ReviewAuthorFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateCatalogSettingsRequest;
use App\Settings\InventorySettings;
use App\Settings\ReviewSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the catalog shows and what customers may say about it.
 *
 * Inventory and reviews share a screen because both decide what a shopper sees
 * on a product page that the product itself does not control.
 */
class CatalogSettingsController extends Controller
{
    public function __construct(
        private InventorySettings $inventory,
        private ReviewSettings $reviews,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Catalog', [
            'inventory' => [
                'track_stock_by_default' => $this->inventory->track_stock_by_default,
                'low_stock_threshold' => $this->inventory->low_stock_threshold,
                'out_of_stock_behavior' => $this->inventory->out_of_stock_behavior,
                'allow_backorders_by_default' => $this->inventory->allow_backorders_by_default,
            ],
            'reviews' => [
                'reviews_enabled' => $this->reviews->reviews_enabled,
                'require_verified_purchase' => $this->reviews->require_verified_purchase,
                'auto_approve' => $this->reviews->auto_approve,
                'author_display_format' => $this->reviews->author_display_format,
            ],
            'authorFormats' => ReviewAuthorFormat::options(),
        ]);
    }

    public function update(UpdateCatalogSettingsRequest $request): RedirectResponse
    {
        $this->inventory->fill($request->inventoryValues())->save();
        $this->reviews->fill($request->reviewValues())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog settings saved.')]);

        return to_route('admin.settings.catalog');
    }
}

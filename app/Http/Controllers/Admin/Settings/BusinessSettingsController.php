<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateBusinessSettingsRequest;
use App\Settings\BusinessSettings;
use App\Settings\CurrencySettings;
use App\Settings\LocalizationSettings;
use App\Support\StorefrontCache;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who the store is and where it trades.
 *
 * Three settings groups share this screen because a staff member setting up a
 * shop answers them in one sitting: the legal entity, the region, and how that
 * region's money is written down.
 *
 * `business.tax_pin` is encrypted at rest. It is still shown here — a KRA PIN
 * is the store's own registration detail and a settings manager has to be able
 * to correct it — unlike the gateway secret on the checkout screen, which is a
 * credential and is write-only.
 */
class BusinessSettingsController extends Controller
{
    public function __construct(
        private BusinessSettings $business,
        private LocalizationSettings $localization,
        private CurrencySettings $currency,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Business', [
            'business' => [
                'legal_name' => $this->business->legal_name,
                'registration_number' => $this->business->registration_number,
                'tax_pin' => $this->business->tax_pin,
                'contact_email' => $this->business->contact_email,
                'contact_phone' => $this->business->contact_phone,
                'address' => $this->business->address,
                'business_hours' => $this->business->business_hours,
            ],
            'localization' => [
                'currency' => $this->localization->currency,
                'weight_unit' => $this->localization->weight_unit,
                'dimension_unit' => $this->localization->dimension_unit,
                'timezone' => $this->localization->timezone,
            ],
            'currency' => [
                'symbol' => $this->currency->symbol,
                'symbol_position' => $this->currency->symbol_position,
                'decimals' => $this->currency->decimals,
                'thousand_separator' => $this->currency->thousand_separator,
                'decimal_separator' => $this->currency->decimal_separator,
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        $this->business->fill($request->businessValues())->save();
        $this->localization->fill($request->localizationValues())->save();
        $this->currency->fill($request->currencyValues())->save();

        // The legal name, contact details and address are the Organization
        // block every page publishes in its structured data.
        StorefrontCache::forgetSeo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business settings saved.')]);

        return to_route('admin.settings.business');
    }
}

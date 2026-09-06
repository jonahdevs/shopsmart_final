<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateShippingSettingsRequest;
use App\Models\TaxClass;
use App\Settings\CurrencySettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What delivery costs and what tax is added to it.
 *
 * Both money fields are cents on the way in and whole currency units on the
 * way out, converted through {@see Money}. The free-shipping threshold is the
 * single authority the checkout pricer reads, so it is edited here and nowhere
 * else.
 */
class ShippingSettingsController extends Controller
{
    public function __construct(
        private ShippingSettings $shipping,
        private TaxSettings $tax,
        private CurrencySettings $currency,
        private Money $money,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Shipping', [
            'shipping' => [
                'local_pickup_enabled' => $this->shipping->local_pickup_enabled,
                'pickup_address' => $this->shipping->pickup_address,
                'flat_rate' => $this->money->toMajor($this->shipping->flat_rate_cents),
                'free_shipping_threshold' => $this->money->toMajor($this->shipping->free_shipping_threshold_cents),
            ],
            'tax' => [
                'tax_enabled' => $this->tax->tax_enabled,
                'default_tax_class_id' => $this->tax->default_tax_class_id,
                'prices_include_tax' => $this->tax->prices_include_tax,
            ],
            'taxClasses' => $this->taxClasses(),
            'currencySymbol' => $this->currency->symbol,
        ]);
    }

    public function update(UpdateShippingSettingsRequest $request): RedirectResponse
    {
        $this->shipping->fill($request->shippingValues())->save();
        $this->tax->fill($request->taxValues())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping and tax settings saved.')]);

        return to_route('admin.settings.shipping');
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function taxClasses(): array
    {
        return array_values(TaxClass::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TaxClass $taxClass): array => [
                'value' => $taxClass->getKey(),
                'label' => $taxClass->name,
            ])
            ->all());
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateCheckoutSettingsRequest;
use App\Settings\CheckoutSettings;
use App\Settings\CurrencySettings;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rules the checkout enforces and the ways money can reach the store.
 *
 * The minimum order value is stored in cents and edited in whole currency
 * units, converted both ways through {@see Money} — the form never sees a
 * cents figure and the settings class never sees a major one.
 *
 * `paystack_secret_key` is deliberately absent from the props. It is a
 * credential rather than a store detail: the screen reports only whether one is
 * configured, and an empty field on save leaves the stored key alone.
 */
class CheckoutSettingsController extends Controller
{
    public function __construct(
        private CheckoutSettings $checkout,
        private PaymentSettings $payments,
        private PaymentApiSettings $paymentApi,
        private CurrencySettings $currency,
        private Money $money,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Checkout', [
            'checkout' => [
                'min_order_value' => $this->money->toMajor($this->checkout->min_order_value_cents),
                'order_prefix' => $this->checkout->order_prefix,
                'guest_checkout_enabled' => $this->checkout->guest_checkout_enabled,
            ],
            'payments' => [
                'paystack_enabled' => $this->payments->paystack_enabled,
                'bank_transfer_enabled' => $this->payments->bank_transfer_enabled,
                'bank_details' => $this->payments->bank_details,
                'cash_on_delivery_enabled' => $this->payments->cash_on_delivery_enabled,
            ],
            'paymentApi' => [
                'paystack_public_key' => $this->paymentApi->paystack_public_key,
                'paystack_secret_key_set' => (string) $this->paymentApi->paystack_secret_key !== '',
            ],
            'currencySymbol' => $this->currency->symbol,
        ]);
    }

    public function update(UpdateCheckoutSettingsRequest $request): RedirectResponse
    {
        $this->checkout->fill($request->checkoutValues())->save();
        $this->payments->fill($request->paymentValues())->save();
        $this->paymentApi
            ->fill($request->paymentApiValues($this->paymentApi->paystack_secret_key))
            ->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checkout settings saved.')]);

        return to_route('admin.settings.checkout');
    }
}

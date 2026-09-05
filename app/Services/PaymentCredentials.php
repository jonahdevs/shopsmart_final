<?php

namespace App\Services;

use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;

/**
 * Where the gateway keys come from, and whether the gateway is usable.
 *
 * Settings win over config. An admin can paste live keys into the settings
 * screen without a redeploy, while `.env` stays the way a developer configures
 * a local or CI environment. Nothing else in the application reads the keys —
 * they exist in exactly one place so a future gateway, or a key rotation, has
 * one seam to touch.
 *
 * `paystackEnabled()` deliberately checks for a secret key as well as the
 * toggle: a gateway switched on with no key configured is not enabled, it is
 * broken, and the storefront must not offer it.
 */
class PaymentCredentials
{
    public function __construct(
        private PaymentSettings $paymentSettings,
        private PaymentApiSettings $apiSettings,
    ) {}

    public function paystackSecretKey(): string
    {
        return $this->apiSettings->paystack_secret_key
            ?: (string) config('services.paystack.secret_key', '');
    }

    public function paystackPublicKey(): string
    {
        return $this->apiSettings->paystack_public_key
            ?: (string) config('services.paystack.public_key', '');
    }

    public function paystackEnabled(): bool
    {
        return $this->paymentSettings->paystack_enabled && $this->paystackSecretKey() !== '';
    }

    public function bankTransferEnabled(): bool
    {
        return $this->paymentSettings->bank_transfer_enabled;
    }

    public function bankDetails(): string
    {
        return $this->paymentSettings->bank_details;
    }

    public function cashOnDeliveryEnabled(): bool
    {
        return $this->paymentSettings->cash_on_delivery_enabled;
    }

    /**
     * The payment methods the store will actually accept right now.
     *
     * One list, used both to render the choices and to validate the one that
     * comes back, so a method can never be submitted that the page would not
     * have offered.
     *
     * @return list<string>
     */
    public function enabledMethods(): array
    {
        return array_keys(array_filter([
            'paystack' => $this->paystackEnabled(),
            'bank_transfer' => $this->bankTransferEnabled(),
            'cash_on_delivery' => $this->cashOnDeliveryEnabled(),
        ]));
    }
}

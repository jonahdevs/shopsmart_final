<?php

namespace App\Http\Requests\Admin\Settings;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout rules, the payment methods offered, and the gateway credentials
 * behind them.
 *
 * `min_order_value` is typed in whole currency units and stored in cents;
 * the conversion goes through {@see Money} rather than a `* 100` here, so the
 * one definition of a minor unit stays in one place.
 *
 * The Paystack secret key is write-only. It is never sent to the browser, and
 * leaving the field blank means "keep what is stored" — there is no way to
 * clear it by accidentally saving a form that could not show it.
 */
class UpdateCheckoutSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_order_value' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'order_prefix' => ['nullable', 'string', 'max:10'],
            'guest_checkout_enabled' => ['nullable', 'boolean'],

            'paystack_enabled' => ['nullable', 'boolean'],
            'bank_transfer_enabled' => ['nullable', 'boolean'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
            'cash_on_delivery_enabled' => ['nullable', 'boolean'],

            'paystack_public_key' => ['nullable', 'string', 'max:255'],
            'paystack_secret_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function checkoutValues(): array
    {
        return [
            'min_order_value_cents' => app(Money::class)->toMinor($this->string('min_order_value')->value()),
            'order_prefix' => $this->string('order_prefix')->trim()->value(),
            'guest_checkout_enabled' => $this->boolean('guest_checkout_enabled'),
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public function paymentValues(): array
    {
        return [
            'paystack_enabled' => $this->boolean('paystack_enabled'),
            'bank_transfer_enabled' => $this->boolean('bank_transfer_enabled'),
            'bank_details' => $this->string('bank_details')->trim()->value(),
            'cash_on_delivery_enabled' => $this->boolean('cash_on_delivery_enabled'),
        ];
    }

    /**
     * The public key travels normally; the secret is only written when the
     * staff member actually typed a new one.
     *
     * @return array<string, string|null>
     */
    public function paymentApiValues(?string $currentSecret): array
    {
        $secret = $this->string('paystack_secret_key')->trim()->value();

        return [
            'paystack_public_key' => $this->string('paystack_public_key')->trim()->value() ?: null,
            'paystack_secret_key' => $secret !== '' ? $secret : $currentSecret,
        ];
    }
}

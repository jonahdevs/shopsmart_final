<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Which payment methods the checkout offers. Paystack is the only online
 * gateway; the remaining options are offline arrangements.
 */
class PaymentSettings extends Settings
{
    public bool $paystack_enabled;

    public bool $bank_transfer_enabled;

    public string $bank_details;

    public bool $cash_on_delivery_enabled;

    public static function group(): string
    {
        return 'payments';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['bank_details'];
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Paystack API credentials. The secret key is encrypted at rest and must never
 * be exposed to the client.
 */
class PaymentApiSettings extends Settings
{
    public ?string $paystack_public_key;

    public ?string $paystack_secret_key;

    public static function group(): string
    {
        return 'payment_api';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['paystack_secret_key'];
    }
}

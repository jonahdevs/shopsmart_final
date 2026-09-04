<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The legal entity behind the store: registration details used on invoices and
 * the contact information shown to customers.
 */
class BusinessSettings extends Settings
{
    public string $legal_name;

    public string $registration_number;

    public string $tax_pin;

    public string $contact_email;

    public string $contact_phone;

    public string $address;

    public string $business_hours;

    public static function group(): string
    {
        return 'business';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['tax_pin'];
    }
}

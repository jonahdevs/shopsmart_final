<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The legal entity, the region it trades in and how it quotes money.
 *
 * Almost every field is `nullable` even though the settings property is a
 * non-nullable string: the global `ConvertEmptyStringsToNull` middleware turns
 * a cleared text input into null before validation sees it, and a store may
 * legitimately have no registration number. The value methods below cast back
 * to a string, so the settings class never receives one.
 */
class UpdateBusinessSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_pin' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_hours' => ['nullable', 'string', 'max:1000'],

            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'weight_unit' => ['required', Rule::in(['g', 'kg', 'lb', 'oz'])],
            'dimension_unit' => ['required', Rule::in(['mm', 'cm', 'm', 'in'])],
            'timezone' => ['required', 'timezone'],

            'symbol' => ['required', 'string', 'max:8'],
            'symbol_position' => ['required', Rule::in(['before', 'after'])],
            'decimals' => ['required', 'integer', 'between:0,4'],
            // A blank thousand separator is a real choice ("24000"), so it is
            // allowed; a blank decimal separator is not, because a store that
            // renders decimals needs something to put between them.
            'thousand_separator' => ['nullable', 'string', 'max:1'],
            'decimal_separator' => ['required', 'string', 'size:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function businessValues(): array
    {
        return [
            'legal_name' => $this->text('legal_name'),
            'registration_number' => $this->text('registration_number'),
            'tax_pin' => $this->text('tax_pin'),
            'contact_email' => $this->text('contact_email'),
            'contact_phone' => $this->text('contact_phone'),
            'address' => $this->text('address'),
            'business_hours' => $this->text('business_hours'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function localizationValues(): array
    {
        return [
            'currency' => mb_strtoupper($this->text('currency')),
            'weight_unit' => $this->text('weight_unit'),
            'dimension_unit' => $this->text('dimension_unit'),
            'timezone' => $this->text('timezone'),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public function currencyValues(): array
    {
        return [
            'symbol' => $this->text('symbol'),
            'symbol_position' => $this->text('symbol_position'),
            'decimals' => $this->integer('decimals'),
            'thousand_separator' => $this->text('thousand_separator'),
            'decimal_separator' => $this->text('decimal_separator'),
        ];
    }

    private function text(string $key): string
    {
        return $this->string($key)->trim()->value();
    }
}

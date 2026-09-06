<?php

namespace App\Http\Requests\Admin\Settings;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delivery charges and VAT behaviour.
 *
 * Both money fields are typed in whole currency units and stored in cents via
 * {@see Money}; the settings properties keep their `_cents` suffix so nothing
 * downstream has to guess which side of the conversion it is on.
 */
class UpdateShippingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'local_pickup_enabled' => ['nullable', 'boolean'],
            'pickup_address' => ['nullable', 'string', 'max:1000'],
            'flat_rate' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0', 'max:100000000'],

            'tax_enabled' => ['nullable', 'boolean'],
            'default_tax_class_id' => ['nullable', 'integer', 'exists:tax_classes,id'],
            'prices_include_tax' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function shippingValues(): array
    {
        $money = app(Money::class);

        return [
            'local_pickup_enabled' => $this->boolean('local_pickup_enabled'),
            'pickup_address' => $this->string('pickup_address')->trim()->value(),
            'flat_rate_cents' => $money->toMinor($this->string('flat_rate')->value()),
            'free_shipping_threshold_cents' => $money->toMinor($this->string('free_shipping_threshold')->value()),
        ];
    }

    /**
     * @return array<string, bool|int|null>
     */
    public function taxValues(): array
    {
        $taxClassId = $this->input('default_tax_class_id');

        return [
            'tax_enabled' => $this->boolean('tax_enabled'),
            'default_tax_class_id' => is_numeric($taxClassId) ? (int) $taxClassId : null,
            'prices_include_tax' => $this->boolean('prices_include_tax'),
        ];
    }
}

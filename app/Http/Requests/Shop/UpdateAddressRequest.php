<?php

namespace App\Http\Requests\Shop;

use App\Concerns\CheckoutValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An edit to an entry in the customer's address book.
 *
 * The same field rules as creating one — an address is either complete or it is
 * not, whichever way it got here. `user_id` is not accepted, and is not written
 * either: an address cannot change hands.
 */
class UpdateAddressRequest extends FormRequest
{
    use CheckoutValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->addressRules();
    }

    /**
     * The attributes to write.
     *
     * Deliberately not named `attributes()`: that is FormRequest's hook for
     * custom validation-message names.
     *
     * @return array<string, mixed>
     */
    public function addressAttributes(): array
    {
        return [
            ...$this->safe()->except('is_default'),
            'is_default' => $this->boolean('is_default'),
        ];
    }
}

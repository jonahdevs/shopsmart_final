<?php

namespace App\Http\Requests\Shop;

use App\Concerns\CheckoutValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new entry for the customer's address book.
 *
 * `user_id` is never accepted from the request — it comes from the session, so
 * an address cannot be filed against someone else's account.
 */
class StoreAddressRequest extends FormRequest
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
     * The address attributes, with the owner attached.
     *
     * Deliberately not named `attributes()`: that is FormRequest's hook for
     * custom validation-message names, and overriding it here would silently
     * break every message this request produces.
     *
     * @return array<string, mixed>
     */
    public function addressAttributes(): array
    {
        return [
            ...$this->safe()->except('is_default'),
            'user_id' => $this->user()?->getKey(),
            'is_default' => $this->boolean('is_default'),
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The one thing staff may change about a customer: their display name.
 *
 * The email address is deliberately absent. Changing it would move where a
 * password reset lands, which turns a support tool into account takeover — the
 * customer changes their own email from their own settings page, confirming it
 * with their own password.
 */
class UpdateCustomerRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

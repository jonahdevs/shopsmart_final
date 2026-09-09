<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * How hard it is to get in, and how hard it is to guess your way in.
 */
class UpdateSecuritySettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'require_two_factor' => ['nullable', 'boolean'],
            /*
              Bounded at both ends. One attempt a minute locks out anyone with a
              password manager that fills the wrong entry once; sixty is loose
              enough that the limiter has stopped being one.
            */
            'login_attempts_per_minute' => ['required', 'integer', 'min:3', 'max:60'],
        ];
    }

    /**
     * @return array{require_two_factor: bool, login_attempts_per_minute: int}
     */
    public function securityValues(): array
    {
        return [
            'require_two_factor' => $this->boolean('require_two_factor'),
            'login_attempts_per_minute' => $this->integer('login_attempts_per_minute'),
        ];
    }
}

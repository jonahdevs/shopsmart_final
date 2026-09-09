<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Whether the shop floor is open to shoppers.
 */
class UpdateMaintenanceSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'maintenance_mode' => ['nullable', 'boolean'],
            /*
              Required rather than nullable: the message is the only thing a
              closed shop says, and an empty one leaves the error page with the
              generic sentence a shopper has already been shown for every other
              503.
            */
            'maintenance_message' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{maintenance_mode: bool, maintenance_message: string}
     */
    public function maintenanceValues(): array
    {
        return [
            'maintenance_mode' => $this->boolean('maintenance_mode'),
            'maintenance_message' => $this->string('maintenance_message')->trim()->value(),
        ];
    }
}

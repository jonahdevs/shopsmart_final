<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Whether the shop floor is open, and what a shopper is told while it is not.
 *
 * Deliberately not Laravel's own `artisan down`, which writes a file the
 * application cannot edit from a form and which takes the admin panel down with
 * it. This is a settings-driven close: staff keep working, shoppers get a 503.
 */
class MaintenanceSettings extends Settings
{
    public bool $maintenance_mode;

    public string $maintenance_message;

    public static function group(): string
    {
        return 'maintenance';
    }
}

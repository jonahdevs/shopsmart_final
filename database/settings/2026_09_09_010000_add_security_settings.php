<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The Security screen, the second under the System tab.
 *
 * `require_two_factor` starts off. Turning it on locks every staff member
 * without a confirmed authenticator out of the admin panel until they enrol,
 * which is the right default to arrive at deliberately rather than by upgrade.
 *
 * The throttle keeps Fortify's own five-a-minute as its default, so nothing
 * changes for a store that never opens this screen.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security.require_two_factor', false);
        $this->migrator->add('security.login_attempts_per_minute', 5);
    }
};

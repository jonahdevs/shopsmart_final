<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Opens the System tab, which was declared in the settings taxonomy with
 * nothing behind it.
 *
 * Maintenance is the first screen to land there. The message is stored rather
 * than hard-coded because the only useful thing a closed shop can say is when
 * it will open again, and that changes every time.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('maintenance.maintenance_mode', false);
        $this->migrator->add(
            'maintenance.maintenance_message',
            'We are making a few changes and will be back shortly. Thank you for your patience.',
        );
    }
};

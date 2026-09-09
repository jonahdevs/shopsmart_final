<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * A retention window for the visitor log.
 *
 * `visitors` is the third trail in this application that records what a person
 * did rather than what they bought, and it is the only one that stores an IP
 * address. It therefore needs the same treatment the browsing history and the
 * activity log already get: a window staff can see and change, and a nightly
 * command that enforces it.
 *
 * Ninety days by default, which is shorter than either of the other two on
 * purpose. Nothing on the dashboard looks further back than thirty days, so a
 * longer window would be keeping identifiable rows nobody reads.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('legal.visitor_retention_days', 90);
    }

    public function down(): void
    {
        $this->migrator->delete('legal.visitor_retention_days');
    }
};

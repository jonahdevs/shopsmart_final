<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Takes a backup off the web request that asked for one.
 *
 * A full backup dumps the database and zips the application, which on any real
 * store outlasts a request. Queueing it means the screen answers immediately
 * and the archive appears in the list when it is done, rather than the staff
 * member watching a spinner until the request times out halfway through a zip.
 */
class RunStoreBackup implements ShouldQueue
{
    use Queueable;

    /**
     * The backup can run long. The default 60 seconds is not enough for a
     * catalog with media behind it.
     */
    public int $timeout = 1800;

    /**
     * One attempt. A retry would leave a second half-written archive behind,
     * and the staff member can simply ask again.
     */
    public int $tries = 1;

    public function __construct(public bool $databaseOnly = false) {}

    public function handle(): void
    {
        Artisan::call('backup:run', $this->databaseOnly ? ['--only-db' => true] : []);
    }
}

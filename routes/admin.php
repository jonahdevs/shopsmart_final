<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| PLACEHOLDER — owned by the phase 7c lead, not by the settings work.
|
| It exists here only so routes/admin/settings.php is actually registered:
| without it Wayfinder emits no actions for the settings controllers, the Vue
| pages cannot import them, and nothing about the section can be tested. It
| declares the group the settings route file documents itself against and
| nothing else — no dashboard, orders or payments routes.
|
| `staff` guards the door; what a staff member may then do is decided per route
| by `can:` middleware inside the required files.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'staff'])
    ->group(function (): void {
        require __DIR__.'/admin/settings.php';
    });

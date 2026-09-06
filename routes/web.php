<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\MailPreviewController;
use Illuminate\Support\Facades\Route;

/*
| `dashboard` is where Fortify sends everyone after signing in, so the name has
| to stay. What it renders depends on who arrived: see DashboardController.
*/
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

/*
| Renders each transactional email in the browser. Local only — this page will
| render any of the store's emails to anyone who asks for one, which is fine on
| a laptop and is not fine on a deployed site.
*/
if (app()->environment('local')) {
    Route::get('dev/mail-preview', MailPreviewController::class)->name('dev.mail-preview');
}

require __DIR__.'/shop.php';
require __DIR__.'/account.php';
require __DIR__.'/settings.php';

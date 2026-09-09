<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Middleware\EnsureStoreIsOpen;
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

/*
| The shop floor is the only thing maintenance mode closes. Auth, the account
| pages and the admin panel stay reachable so staff can sign in to a closed shop
| and fix whatever it was closed for, and so a customer is not locked out of
| their own orders. EnsureStoreIsOpen lets staff and the gateway webhook through.
*/
Route::middleware(EnsureStoreIsOpen::class)->group(function (): void {
    require __DIR__.'/shop.php';
});

require __DIR__.'/account.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

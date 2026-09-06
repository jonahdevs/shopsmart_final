<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| `staff` guards the door — it refuses anyone holding no role at all. What a
| staff member may then do is decided per route by `can:`, against the
| permissions PermissionSeeder defines. The two are deliberately separate: one
| question is "does this person work here", the other is "may they do this".
|
| The sidebar renders from the same permission list (shared by
| HandleInertiaRequests), so a Manager is never shown a link that `can:` would
| then refuse. Navigation and authorisation cannot drift because there is one
| source for both.
|
| Orders are bound by `order_number` through the model's own route key, so an
| admin URL never exposes a sequential primary key.
|
*/

Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware('can:orders.view')->group(function (): void {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        });

        Route::middleware('can:orders.manage')->group(function (): void {
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
            Route::patch('orders/{order}/note', [OrderController::class, 'updateNote'])->name('orders.note');
        });

        Route::middleware('can:payments.view')->group(function (): void {
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        });
    });

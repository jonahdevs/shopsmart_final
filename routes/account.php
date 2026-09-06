<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\OrderReceiptController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Shop\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
|
| Everything a signed-in shopper owns, under one prefix. `customer` keeps staff
| out for the same reason it keeps them out of checkout: these pages are the
| shopper's own record, and a staff account has no orders, no addresses and no
| reviews to show.
|
| Order history lives here rather than at the top level — it is part of the
| account, not a section of the storefront — but the route NAMES are unchanged
| (`orders.index`, `orders.show`), so every Wayfinder import and every link
| already written against them keeps working across the move.
|
| Nothing in this file takes a user id. The pages that take an id at all take an
| order number or an address, and each of those checks ownership with a 404.
|
*/

Route::middleware(['auth', 'verified', 'customer'])->prefix('account')->group(function (): void {
    Route::get('/', [AccountController::class, 'dashboard'])->name('account.dashboard');

    Route::get('addresses', [AccountController::class, 'addresses'])->name('account.addresses');

    Route::get('reviews', [AccountController::class, 'reviews'])->name('account.reviews');
    Route::get('reviews/{product:slug}', [ReviewController::class, 'create'])->name('account.reviews.create');
    Route::post('reviews/{product:slug}', [ReviewController::class, 'store'])->name('account.reviews.store');

    Route::get('recently-viewed', [AccountController::class, 'recentlyViewed'])->name('account.recently-viewed');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');

    // The order as a document. Its own controller rather than a method on
    // OrderController: it returns a PDF, not a page, and it is the one route
    // here that boots a headless browser.
    Route::get('orders/{order:order_number}/receipt', OrderReceiptController::class)->name('orders.receipt');
});

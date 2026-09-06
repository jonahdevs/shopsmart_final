<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customers, reviews and coupons
|--------------------------------------------------------------------------
|
| Required from routes/admin.php inside the group that already applies the
| `admin` prefix, the `admin.` name prefix and the ['auth', 'verified', 'staff']
| middleware — so nothing here restates any of that. What each route adds is the
| one thing the group cannot decide for it: which permission admits a staff
| member to it.
|
| The split follows PermissionSeeder. Customers separate reading from writing,
| because Support may look a shopper up but must not edit them. Reviews and
| coupons do not: moderating is the only thing anyone does to a review, and
| there is no useful role that may read a discount's terms but not set them.
|
| Coupons bind by primary key rather than by code. A code is edited from this
| very screen, and a URL that changed the moment somebody fixed a typo in it
| would break every bookmark and every link in the audit trail.
|
*/

Route::middleware('can:customers.view')->group(function (): void {
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])
        ->whereNumber('customer')
        ->name('customers.show');
});

Route::middleware('can:customers.manage')->group(function (): void {
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])
        ->whereNumber('customer')
        ->name('customers.update');
});

Route::middleware('can:reviews.manage')->group(function (): void {
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});

Route::middleware('can:marketing.manage')->group(function (): void {
    Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('coupons/{coupon}', [CouponController::class, 'show'])
        ->whereNumber('coupon')
        ->name('coupons.show');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])
        ->whereNumber('coupon')
        ->name('coupons.edit');
    Route::put('coupons/{coupon}', [CouponController::class, 'update'])
        ->whereNumber('coupon')
        ->name('coupons.update');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])
        ->whereNumber('coupon')
        ->name('coupons.destroy');
});

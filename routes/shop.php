<?php

use App\Http\Controllers\Payments\PaystackWebhookController;
use App\Http\Controllers\Shop\AddressController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CheckoutCouponController;
use App\Http\Controllers\Shop\CompareController;
use App\Http\Controllers\Shop\ConsentController;
use App\Http\Controllers\Shop\DefaultAddressController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchController;
use App\Http\Controllers\Shop\SeoController;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
|
| The public catalog. Every page is open to guests; nothing here is behind
| auth. `categories` is declared before the `shop/{category}` binding purely
| for readability — the two paths cannot collide.
|
*/

/*
| Routes rather than files in public/, because both answer to a setting staff
| can change: turning indexing off has to take effect now, and a robots.txt
| written to disk at deploy time would still be inviting crawlers hours later.
| The static public/robots.txt was removed for the same reason — the web server
| serves a real file before it ever reaches Laravel.
*/
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/', HomeController::class)->name('home');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

Route::get('shop', [CatalogController::class, 'index'])->name('catalog');
Route::get('shop/{category:slug}', [CategoryController::class, 'show'])->name('category.show');

Route::get('product/{product:slug}', [ProductController::class, 'show'])->name('product.show');

// The results page: the catalog listing with a term on it, so it reads the same
// query string and is bookmarkable and shareable like any other listing.
Route::get('search', [SearchController::class, 'index'])->name('search');

// Fires on every keystroke past the second character, so it is throttled per
// IP rather than left to hammer the search engine.
Route::get('search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('search.suggest');

/*
|--------------------------------------------------------------------------
| Cart, wishlist and compare
|--------------------------------------------------------------------------
|
| Also open to guests: the session carries all three, and a signed-in shopper
| just gets the same thing mirrored to the database. Requiring an account to
| put something in a basket is the fastest way to lose the sale.
|
| The bulk `clear` routes sit on their own paths so the collection verb stays
| free for removing a single entry, which is the far more common action.
|
*/

Route::get('cart', [CartController::class, 'index'])->name('cart.index');
Route::post('cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('cart', [CartController::class, 'update'])->name('cart.update');
Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('cart/all', [CartController::class, 'clear'])->name('cart.clear');

Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
Route::delete('wishlist', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
Route::delete('wishlist/all', [WishlistController::class, 'clear'])->name('wishlist.clear');

Route::get('compare', [CompareController::class, 'index'])->name('compare.index');
Route::post('compare', [CompareController::class, 'store'])->name('compare.store');
Route::delete('compare', [CompareController::class, 'destroy'])->name('compare.destroy');
Route::delete('compare/all', [CompareController::class, 'clear'])->name('compare.clear');

/*
|--------------------------------------------------------------------------
| Cookie consent
|--------------------------------------------------------------------------
|
| Open to guests, because it is guests the banner exists for. The answer is
| written server-side into a cookie so the next document can be assembled with
| only the measurement tags this visitor allowed — see App\Support\Consent.
|
| Throttled: it is an unauthenticated write, and there is no reason for anyone
| to change their mind faster than this.
|
*/

Route::post('consent', [ConsentController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('consent.store');

/*
|--------------------------------------------------------------------------
| Checkout and payment
|--------------------------------------------------------------------------
|
| The one part of the storefront that is not open to guests. `customer` keeps
| staff out: their accounts exist to run the store, and an order placed by one
| would pollute the figures it is their job to read.
|
| The delivery-vs-collection choice travels in the query string rather than the
| session, so it is a plain link the shopper can go back through.
|
| The order pages themselves moved to routes/account.php under /account/orders,
| keeping their names. `orders.show` still doubles as the confirmation page —
| the order IS the receipt, so there is no separate throwaway page that stops
| working on a refresh.
|
*/

Route::middleware(['auth', 'verified', 'customer'])->group(function () {
    Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::post('checkout/coupon', [CheckoutCouponController::class, 'store'])->name('checkout.coupon.store');
    Route::delete('checkout/coupon', [CheckoutCouponController::class, 'destroy'])->name('checkout.coupon.destroy');

    // The address book, kept together here because checkout writes to it as
    // well as the account area reading it. Every action scopes to the
    // signed-in user; promoting a default is its own controller because it
    // necessarily demotes the others.
    Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::patch('addresses/{address}/default', [DefaultAddressController::class, 'update'])->name('addresses.default');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    // Paying is its own step, repeatable for as long as the order is unpaid: a
    // closed popup or a dropped connection must not cost the shopper the order
    // they already committed to.
    //
    // `start` and `verify` are throttled because each one reaches the gateway,
    // and a loop here would be both a bill and a way to probe references.
    Route::get('pay/{order:order_number}', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('pay/{order:order_number}/start', [PaymentController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('payment.start');
    Route::post('pay/{order:order_number}/verify', [PaymentController::class, 'verify'])
        ->middleware('throttle:20,1')
        ->name('payment.verify');
});

/*
|--------------------------------------------------------------------------
| Gateway webhook
|--------------------------------------------------------------------------
|
| Server-to-server, so it carries no session and no CSRF token — the HMAC
| signature over the raw body is the only thing authenticating it, and
| bootstrap/app.php exempts this path from CSRF for that reason.
|
| It sits outside every auth group on purpose: Paystack is not a user.
|
*/

Route::post('api/webhooks/paystack', PaystackWebhookController::class)
    ->name('webhooks.paystack');

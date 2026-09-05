<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CompareController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchController;
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

Route::get('/', HomeController::class)->name('home');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

Route::get('shop', [CatalogController::class, 'index'])->name('catalog');
Route::get('shop/{category:slug}', [CategoryController::class, 'show'])->name('category.show');

Route::get('product/{product:slug}', [ProductController::class, 'show'])->name('product.show');

// Fires on every keystroke past the second character, so it is throttled per
// IP rather than left to hammer the search engine.
Route::get('search/suggest', SearchController::class)
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

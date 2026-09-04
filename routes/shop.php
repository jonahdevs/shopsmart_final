<?php

use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchController;
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

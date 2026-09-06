<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog administration
|--------------------------------------------------------------------------
|
| Required from routes/admin.php inside a group that already applies
| ->prefix('admin'), ->name('admin.') and ->middleware(['auth','verified',
| 'staff']). Nothing here re-declares any of that; the only middleware these
| routes add is the `can:` that decides which staff member may do what.
|
| Reading the catalog and changing it are separate permissions:
| `products.view` gets the products table, `products.manage` is needed to
| create, edit or remove one. Categories, brands and attributes are catalog
| *structure* rather than merchandise, and move together under a single
| `catalog.manage` — a role that may restructure the taxonomy may restructure
| all of it.
|
| Products, categories and brands are bound by slug through their models' own
| route keys. Attributes are not: they never appear in a storefront URL, so
| there is no slug worth putting in an admin one.
|
| `products/create` is registered before `products/{product}/edit` only for
| readability — the two patterns cannot collide. `products.restore` binds
| withTrashed because a soft-deleted product is exactly the row it acts on.
|
*/

Route::middleware('can:products.view')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
});

Route::middleware('can:products.manage')->group(function (): void {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');

    // Media is posted on its own route rather than as part of the edit form:
    // a multipart body cannot ride a PATCH everywhere, and an image upload is
    // an action of its own — staff expect a dropped file to land immediately,
    // not on the next save.
    Route::post('products/{product}/media', [ProductController::class, 'storeMedia'])->name('products.media.store');
    Route::delete('products/{product}/media/{media}', [ProductController::class, 'destroyMedia'])->name('products.media.destroy');
});

Route::middleware('can:catalog.manage')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
    Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
    Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
    Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::get('attributes/create', [AttributeController::class, 'create'])->name('attributes.create');
    Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
    Route::get('attributes/{attribute}/edit', [AttributeController::class, 'edit'])->name('attributes.edit');
    Route::patch('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');
});

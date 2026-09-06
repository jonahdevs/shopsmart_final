<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog route registration for tests
|--------------------------------------------------------------------------
|
| routes/admin/catalog.php is written to be `require`d from routes/admin.php
| inside a group that already applies the admin prefix, name prefix and the
| auth/verified/staff middleware. Until that require lands, these tests put the
| routes up themselves in exactly that group, plus the `web` group the route
| file would otherwise inherit from routes/web.php.
|
| Idempotent: once routes/admin.php requires the file, `admin.products.index`
| already exists and this does nothing, so the tests keep passing across the
| change rather than needing to be rewritten by it.
|
| Not a *Test.php file, so PHPUnit's file iterator never collects it; each test
| file requires it explicitly rather than relying on load order.
|
*/

if (! function_exists('registerAdminCatalogRoutes')) {
    function registerAdminCatalogRoutes(): void
    {
        if (Route::has('admin.products.index')) {
            return;
        }

        Route::middleware(['web', 'auth', 'verified', 'staff'])
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/admin/catalog.php'));
    }
}

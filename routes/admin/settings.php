<?php

use App\Http\Controllers\Admin\Settings\BrandingSettingsController;
use App\Http\Controllers\Admin\Settings\BusinessSettingsController;
use App\Http\Controllers\Admin\Settings\CatalogSettingsController;
use App\Http\Controllers\Admin\Settings\CheckoutSettingsController;
use App\Http\Controllers\Admin\Settings\PrivacySettingsController;
use App\Http\Controllers\Admin\Settings\SeoSettingsController;
use App\Http\Controllers\Admin\Settings\ShippingSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Store settings
|--------------------------------------------------------------------------
|
| Required from routes/admin.php INSIDE a group that already applies the
| `admin` prefix, the `admin.` name prefix and the ['auth', 'verified', 'staff']
| middleware — so this file declares none of those. `settings.business` here is
| `admin.settings.business` at /admin/settings/business.
|
| Each route carries `can:settings.manage` itself rather than the group doing
| it: a Manager is staff and passes the door middleware, but system config is
| not theirs, and the permission has to be stated where the reader is looking.
|
| The fifteen settings classes are grouped into seven screens rather than being
| listed one per page. The grouping follows the job a staff member came to do —
| set the shop up, dress it, price delivery — not the class boundaries, which
| exist so a page can read one narrow object rather than a bag of everything.
|
*/

Route::redirect('settings', '/admin/settings/business')->name('settings.index');

Route::get('settings/business', [BusinessSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.business');
Route::put('settings/business', [BusinessSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.business.update');

Route::get('settings/branding', [BrandingSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.branding');
Route::put('settings/branding', [BrandingSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.branding.update');

Route::get('settings/catalog', [CatalogSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.catalog');
Route::put('settings/catalog', [CatalogSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.catalog.update');

Route::get('settings/checkout', [CheckoutSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.checkout');
Route::put('settings/checkout', [CheckoutSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.checkout.update');

Route::get('settings/shipping', [ShippingSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.shipping');
Route::put('settings/shipping', [ShippingSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.shipping.update');

Route::get('settings/seo', [SeoSettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.seo');
Route::put('settings/seo', [SeoSettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.seo.update');

Route::get('settings/privacy', [PrivacySettingsController::class, 'edit'])
    ->middleware('can:settings.manage')
    ->name('settings.privacy');
Route::put('settings/privacy', [PrivacySettingsController::class, 'update'])
    ->middleware('can:settings.manage')
    ->name('settings.privacy.update');

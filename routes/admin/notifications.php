<?php

use App\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification bell
|--------------------------------------------------------------------------
|
| Required from routes/admin.php inside the group that already applies the
| `admin` prefix, the `admin.` name prefix and the ['auth', 'verified', 'staff']
| middleware — so nothing here restates any of that.
|
| Note the absence of a `can:`. Every staff member has a bell; what may appear
| in it is decided per notification type by App\Data\AdminNotificationData, and
| a route-level permission here would have to be either the loosest of those
| types (admitting a click on a row the viewer may not read) or the strictest
| (locking a Support member out of their own order alerts).
|
| `read-all` is declared before `{notification}` on purpose: notification ids
| are uuids and the wildcard would otherwise swallow the literal path.
|
*/

Route::prefix('notifications')->name('notifications.')->group(function (): void {
    Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::get('{notification}', [NotificationController::class, 'show'])->name('show');
});

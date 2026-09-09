<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Who works here, what they may do, and what they did
|--------------------------------------------------------------------------
|
| Required from routes/admin.php inside the group that already applies the
| `admin` prefix, the `admin.` name prefix and the ['auth', 'verified', 'staff']
| middleware — so these are bare route definitions and every name below gains
| `admin.` in front of it.
|
| Three permissions, three separate answers:
|
|   staff.manage   — invite a colleague, change their roles, revoke their access
|   roles.manage   — define what a role *means*. Super Admin only.
|   activity.view  — read the audit trail. Read-only by design: there is
|                    deliberately no route here that writes to activity_log,
|                    because a log staff can edit is not evidence of anything.
|
| Splitting the first two is the point of this section. Someone who may add a
| colleague must not thereby be able to invent a role that grants more than they
| hold and then wear it themselves; the requests refuse to assign a role whose
| permissions the actor does not already have, so `staff.manage` can only ever
| hand out powers its holder already had.
|
*/

/*
| Roles and the people holding them share one screen. It is guarded by
| `staff.manage` rather than `roles.manage` so an Admin — who may hire and
| demote but may not redefine a role — still reaches the staff table; the
| controller hides the role cards from them, and the role routes below refuse
| them on their own.
*/
Route::middleware('can:staff.manage')->group(function (): void {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

    // The screen these two used to have. Kept as a redirect because staff
    // writes and old links both point at it.
    Route::redirect('staff', '/admin/roles')->name('staff.index');

    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::patch('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::post('staff/{user}/invitation', [StaffController::class, 'invite'])->name('staff.invitation');
    Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
});

Route::middleware('can:roles.manage')->group(function (): void {
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    /*
      Read-only, and sharing `roles.manage` on purpose. Permissions are defined
      in code, so there is nothing here to write; the page exists because the
      person deciding what a role means needs to see which roles already hold a
      permission before taking it away from one of them.
    */
    Route::get('permissions', PermissionController::class)->name('permissions.index');
});

Route::middleware('can:activity.view')->group(function (): void {
    Route::get('activity', ActivityController::class)->name('activity.index');
});

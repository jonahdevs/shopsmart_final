<?php

use App\Http\Controllers\Admin\ActivityController;
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

Route::middleware('can:staff.manage')->group(function (): void {
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::patch('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::post('staff/{user}/invitation', [StaffController::class, 'invite'])->name('staff.invitation');
    Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
});

Route::middleware('can:roles.manage')->group(function (): void {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware('can:activity.view')->group(function (): void {
    Route::get('activity', ActivityController::class)->name('activity.index');
});

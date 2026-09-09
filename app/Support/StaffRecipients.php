<?php

namespace App\Support;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;

/**
 * Which staff should hear about something.
 *
 * There is exactly one answer in this application and it is a permission: the
 * people who may already open the screen an alert points at are the people the
 * alert is for. Anything else — a "notify me" flag, a hard-coded role name — is
 * a second access rule that drifts away from the `can:` on the route, and a
 * notification is worth nothing to a recipient who is refused the record.
 *
 * Deliberately not Spatie's own `permission()` scope. That scope throws
 * {@see PermissionDoesNotExist} when the named
 * permission has never been registered, which turns "nobody holds this yet" —
 * a perfectly ordinary state on a fresh database or in a test that never seeded
 * {@see PermissionSeeder} — into a fatal error on the checkout path. An empty
 * recipient list is the honest answer there.
 */
class StaffRecipients
{
    /**
     * Everyone holding this permission, whether granted directly or by a role.
     *
     * @return Collection<int, User>
     */
    public static function withPermission(string $permission): Collection
    {
        return User::query()
            ->where(function (BuilderContract $query) use ($permission): void {
                $query
                    ->whereHas('permissions', fn (BuilderContract $held) => self::named($held, $permission))
                    ->orWhereHas('roles.permissions', fn (BuilderContract $held) => self::named($held, $permission));
            })
            ->get();
    }

    /**
     * @param  BuilderContract<Permission>  $query
     */
    private static function named(BuilderContract $query, string $permission): void
    {
        $query
            ->where('name', $permission)
            ->where('guard_name', PermissionSeeder::GUARD);
    }
}

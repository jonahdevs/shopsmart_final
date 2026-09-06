<?php

namespace App\Data;

use App\Http\Requests\Admin\StaffFormRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One role as a choice on the staff form — and the rule about who may choose it.
 *
 * `assignable` is the anti-escalation rule made visible: a staff member may hand
 * out a role only when every permission that role carries is one they already
 * hold themselves. Without it `staff.manage` would be a back door to every other
 * permission in the system — invite an account, give it Super Admin, sign in as
 * it. The flag is a courtesy to the person filling the form; the refusal itself
 * lives in {@see StaffFormRequest}, on the server,
 * where it cannot be edited out of the page.
 */
#[TypeScript]
class AdminRoleOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $permissionCount,
        /** Whether the signed-in staff member may grant or revoke this role. */
        public bool $assignable,
    ) {}

    /**
     * Every role, flagged with whether this actor may hand it out.
     *
     * @return list<self>
     */
    public static function forActor(User $actor): array
    {
        $held = self::permissionsHeldBy($actor);

        return array_values(self::rolesWithPermissions()
            ->map(fn (Role $role): self => new self(
                id: $role->getKey(),
                name: $role->name,
                permissionCount: $role->permissions->count(),
                assignable: self::isAssignable($role, $held),
            ))
            ->all());
    }

    /**
     * The names of the roles this actor may grant or revoke.
     *
     * @return list<string>
     */
    public static function assignableFor(User $actor): array
    {
        $held = self::permissionsHeldBy($actor);

        return array_values(self::rolesWithPermissions()
            ->filter(fn (Role $role): bool => self::isAssignable($role, $held))
            ->map(fn (Role $role): string => $role->name)
            ->all());
    }

    /**
     * Whether the actor may act on a staff member holding these roles.
     *
     * The mirror of granting: revoking a role you could not have granted is a
     * privilege problem too — it is how a Support account with `staff.manage`
     * would lock out the Admin above it.
     *
     * @param  list<string>  $roleNames
     */
    public static function actorMayManage(User $actor, array $roleNames): bool
    {
        $assignable = self::assignableFor($actor);

        foreach ($roleNames as $role) {
            if (! in_array($role, $assignable, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $held
     */
    private static function isAssignable(Role $role, array $held): bool
    {
        foreach ($role->permissions as $permission) {
            if (! in_array($permission->name, $held, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function permissionsHeldBy(User $actor): array
    {
        return array_values(array_map(
            static fn (Permission $permission): string => $permission->name,
            $actor->getAllPermissions()->all(),
        ));
    }

    /**
     * @return Collection<int, Role>
     */
    private static function rolesWithPermissions(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }
}

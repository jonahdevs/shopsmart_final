<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The permission matrix, grouped the way the names already group themselves.
 *
 * Permissions are named `<resource>.<action>` — the seeder says so — so the
 * resource segment is the heading and nothing has to be listed twice in a
 * lookup table that would then drift from the seeder.
 *
 * `holdable` on each permission is the escalation rule again: a role editor may
 * only put into a role a permission they already hold themselves. Since
 * `roles.manage` currently belongs to Super Admin alone, who holds everything,
 * this is normally the whole matrix — but a store that hands `roles.manage` to
 * a narrower custom role must not thereby have handed out everything else.
 */
#[TypeScript]
class AdminPermissionGroupData extends Data
{
    /**
     * @param  list<AdminPermissionOptionData>  $permissions
     */
    public function __construct(
        public string $resource,
        public string $label,
        public array $permissions,
    ) {}

    /**
     * Every permission, grouped, with the given ones marked granted.
     *
     * @param  list<string>  $granted
     * @return list<self>
     */
    public static function matrix(User $actor, array $granted): array
    {
        $held = array_values(array_map(
            static fn (Permission $permission): string => $permission->name,
            $actor->getAllPermissions()->all(),
        ));

        /** @var array<string, list<AdminPermissionOptionData>> $groups */
        $groups = [];

        foreach (Permission::query()->orderBy('name')->get() as $permission) {
            $resource = str_contains($permission->name, '.')
                ? strstr($permission->name, '.', true)
                : $permission->name;

            $groups[(string) $resource][] = new AdminPermissionOptionData(
                name: $permission->name,
                label: self::actionLabel($permission->name),
                granted: in_array($permission->name, $granted, true),
                holdable: in_array($permission->name, $held, true),
            );
        }

        return array_map(
            static fn (string $resource): self => new self(
                resource: $resource,
                label: ucfirst(str_replace('_', ' ', $resource)),
                permissions: $groups[$resource],
            ),
            array_keys($groups),
        );
    }

    /**
     * "orders.manage" reads as "Manage" under the "Orders" heading.
     */
    private static function actionLabel(string $permission): string
    {
        $action = str_contains($permission, '.')
            ? (string) substr(strrchr($permission, '.') ?: '.', 1)
            : $permission;

        return ucfirst(str_replace('_', ' ', $action));
    }
}

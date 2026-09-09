<?php

namespace App\Data;

use Database\Seeders\PermissionSeeder;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One permission in the permissions table.
 *
 * The table is a reference, not an editor. Permissions are defined in code —
 * {@see PermissionSeeder::PERMISSIONS} is the list, and the `can:` middleware
 * on the routes is what enforces them — so a screen that let staff invent one
 * would create a row nothing checks. What the screen is for is the question a
 * role editor actually has: which roles currently hold this, and therefore who
 * would lose what if it were taken away.
 *
 * `group` and `action` come from the `<resource>.<action>` name rather than a
 * lookup table, for the same reason {@see AdminPermissionGroupData} does it:
 * a table would drift from the seeder the first time someone added a permission
 * and forgot the second file.
 */
#[TypeScript]
class AdminPermissionRowData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
        public string $group,
        public string $groupLabel,
        public array $roles,
        public int $roleCount,
    ) {}

    public static function fromModel(Permission $permission): self
    {
        $group = str_contains($permission->name, '.')
            ? (string) strstr($permission->name, '.', true)
            : $permission->name;

        $roles = array_values(array_map(
            static fn (Role $role): string => $role->name,
            $permission->roles->all(),
        ));

        return new self(
            id: $permission->getKey(),
            name: $permission->name,
            label: self::humanise(self::action($permission->name)),
            group: $group,
            groupLabel: self::humanise($group),
            roles: $roles,
            roleCount: count($roles),
        );
    }

    /** "orders.manage" is "Manage" under the "Orders" heading. */
    private static function action(string $permission): string
    {
        return str_contains($permission, '.')
            ? (string) substr(strrchr($permission, '.') ?: '.', 1)
            : $permission;
    }

    private static function humanise(string $segment): string
    {
        return ucfirst(str_replace('_', ' ', $segment));
    }
}

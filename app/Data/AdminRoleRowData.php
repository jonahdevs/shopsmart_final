<?php

namespace App\Data;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One role in the roles table.
 *
 * `protected` is {@see PermissionSeeder::PROTECTED_ROLES} — Super Admin and
 * Admin are the floor the whole permission system stands on, and editing or
 * deleting either through a form is how a store locks itself out of its own
 * admin panel. The flag greys the buttons out; the refusal is in the requests
 * and the controller.
 *
 * `deletable` additionally requires the role to be empty. A role with members
 * is somebody's access, and deleting it would silently turn every one of them
 * back into a customer.
 *
 * `firstMemberName` is one name, not the list: the card shows a single avatar
 * as a hint that a role is somebody's access rather than an empty definition,
 * and loading every member of every role to draw one initial would be a query
 * per role for something the Staff screen already lists properly.
 */
#[TypeScript]
class AdminRoleRowData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $permissions,
        public int $permissionCount,
        public int $memberCount,
        public ?string $firstMemberName,
        public bool $isProtected,
        public bool $editable,
        public bool $deletable,
    ) {}

    public static function fromModel(Role $role): self
    {
        $permissions = array_values(array_map(
            static fn (Permission $permission): string => $permission->name,
            $role->permissions->all(),
        ));

        $isProtected = in_array($role->name, PermissionSeeder::PROTECTED_ROLES, true);
        $memberCount = (int) ($role->getAttribute('users_count') ?? 0);

        /** @var User|null $firstMember */
        $firstMember = $role->relationLoaded('users') ? $role->users->first() : null;

        return new self(
            id: $role->getKey(),
            name: $role->name,
            permissions: $permissions,
            permissionCount: count($permissions),
            memberCount: $memberCount,
            firstMemberName: $firstMember?->name,
            isProtected: $isProtected,
            editable: ! $isProtected,
            deletable: ! $isProtected && $memberCount === 0,
        );
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * What the role create and edit forms share.
 *
 * The permission matrix is filtered to what the editor already holds. Today
 * `roles.manage` belongs to Super Admin alone, who holds everything, so the
 * filter is a no-op — but the moment a store hands `roles.manage` to a narrower
 * custom role, "may define roles" must not quietly mean "may define a role that
 * grants me everything". The rule keeps the two apart permanently.
 *
 * Protected roles are refused in the subclasses rather than here: creating a
 * role is never protected, and editing one always might be.
 */
abstract class RoleFormRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->targetRole();

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                // Rejecting the protected names outright stops a second "Admin"
                // being created and later mistaken for the real one.
                Rule::notIn($this->protectedNamesOtherThan($role)),
                $role === null
                    ? Rule::unique(Role::class, 'name')->where('guard_name', PermissionSeeder::GUARD)
                    : Rule::unique(Role::class, 'name')->where('guard_name', PermissionSeeder::GUARD)->ignore($role->getKey()),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->holdablePermissionNames())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.not_in' => __('That name belongs to a built-in role.'),
            'permissions.*.in' => __('You can only grant a permission you hold yourself.'),
        ];
    }

    /**
     * The role being edited, or null when one is being created.
     */
    protected function targetRole(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role : null;
    }

    /**
     * The permissions this actor may put into a role.
     *
     * @return list<string>
     */
    protected function holdablePermissionNames(): array
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return [];
        }

        return array_values(array_map(
            static fn (Permission $permission): string => $permission->name,
            $actor->getAllPermissions()->all(),
        ));
    }

    /**
     * Built-in names this submission may not take.
     *
     * A protected role keeps its own name — it is only ever saved with it — so
     * it is excluded from its own check.
     *
     * @return list<string>
     */
    private function protectedNamesOtherThan(?Role $role): array
    {
        return array_values(array_filter(
            PermissionSeeder::PROTECTED_ROLES,
            static fn (string $name): bool => $role === null || $role->name !== $name,
        ));
    }
}

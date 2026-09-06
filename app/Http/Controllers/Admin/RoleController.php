<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminPermissionGroupData;
use App\Data\AdminRoleRowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleFormRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * What a role means.
 *
 * Guarded by `roles.manage`, which {@see PermissionSeeder} gives to Super Admin
 * alone: this is the screen that decides what every other screen is allowed to
 * do, so the store should have very few people on it.
 *
 * Three refusals are enforced here rather than in the page:
 *
 *  - {@see PermissionSeeder::PROTECTED_ROLES} cannot be edited or deleted.
 *    Super Admin is the only role that can restore the others, and Admin is what
 *    the store runs on day to day.
 *  - A role with members cannot be deleted, because deleting it would silently
 *    turn each of them into a customer. Move them first, and the loss of access
 *    is a decision somebody made rather than a consequence they discover.
 *  - A permission the editor does not hold cannot be put into a role — see
 *    {@see RoleFormRequest}.
 *
 * Every write clears Spatie's permission cache. It caches the whole map, and a
 * role that has lost a permission while the cache still grants it is a hole,
 * not a refresh delay.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/roles/Index', [
            'roles' => array_values($roles
                ->map(fn (Role $role): AdminRoleRowData => AdminRoleRowData::fromModel($role))
                ->all()),
            'permissionCount' => Permission::query()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/roles/Create', [
            'groups' => AdminPermissionGroupData::matrix($this->staffMember($request), []),
        ]);
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => (string) $request->validated('name'),
            'guard_name' => PermissionSeeder::GUARD,
        ]);

        $role->syncPermissions($this->submittedPermissions($request));

        $this->forgetPermissionCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role :name created.', ['name' => $role->name])]);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->guardProtected($role);

        $role->load('permissions');

        return Inertia::render('admin/roles/Edit', [
            'role' => AdminRoleRowData::fromModel($role),
            'groups' => AdminPermissionGroupData::matrix(
                $this->staffMember($request),
                array_values(array_map(
                    static fn (Permission $permission): string => $permission->name,
                    $role->permissions->all(),
                )),
            ),
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->guardProtected($role);

        $role->update(['name' => (string) $request->validated('name')]);
        $role->syncPermissions($this->submittedPermissions($request));

        $this->forgetPermissionCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role :name updated.', ['name' => $role->name])]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->guardProtected($role);

        if ($role->users()->exists()) {
            return back()->withErrors([
                'name' => __('This role still has members. Move them to another role first.'),
            ]);
        }

        $name = $role->name;
        $role->delete();

        $this->forgetPermissionCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role :name deleted.', ['name' => $name])]);

        return to_route('admin.roles.index');
    }

    private function guardProtected(Role $role): void
    {
        abort_if(in_array($role->name, PermissionSeeder::PROTECTED_ROLES, true), 403);
    }

    /**
     * @return list<string>
     */
    private function submittedPermissions(RoleStoreRequest|RoleUpdateRequest $request): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_filter((array) $request->validated('permissions', []), 'is_string'));

        return $permissions;
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The signed-in staff member. The route group guarantees one, so this
     * narrows the type rather than making a decision.
     */
    private function staffMember(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

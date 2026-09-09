<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminPermissionGroupData;
use App\Data\AdminRoleOptionData;
use App\Data\AdminRoleRowData;
use App\Data\AdminStaffRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleFormRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Http\Requests\Admin\StaffIndexRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
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
    use BuildsLikeQueries;

    /** Rows per page in the staff table. */
    private const PER_PAGE = 25;

    /**
     * Roles and the people holding them, on one screen.
     *
     * Consolidated on purpose. A role is a definition and a staff member is an
     * instance of it, and keeping them on separate screens meant the question
     * every reader actually arrives with — "who would this affect?" — needed
     * two pages and a memory of what was on the other one.
     *
     * The guard is `staff.manage`, not `roles.manage`. An Admin may hire and
     * demote but may not redefine what a role means, so they get the table and
     * not the cards; `$canManageRoles` is what the page branches on, and the
     * role routes still refuse them on their own.
     */
    public function index(StaffIndexRequest $request): Response
    {
        $viewer = $this->staffMember($request);
        $canManageRoles = $viewer->can('roles.manage');

        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') ?? 'asc';

        $staff = User::query()
            ->whereHas('roles')
            ->with('roles:id,name')
            ->tap(fn (Builder $query) => $this->applyStaffFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $assignable = AdminRoleOptionData::assignableFor($viewer);

        return Inertia::render('admin/roles/Index', [
            'roles' => $canManageRoles ? $this->roleCards() : [],
            'permissionCount' => $canManageRoles ? Permission::query()->count() : 0,
            'canManageRoles' => $canManageRoles,
            'staff' => array_values(array_map(
                fn (User $user): AdminStaffRowData => AdminStaffRowData::fromModel($user, $viewer, $assignable),
                $staff->items(),
            )),
            'pagination' => PaginationData::fromPaginator($staff),
            'filters' => [
                'search' => $request->validated('search'),
                'role' => $request->validated('role'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roleOptions' => AdminRoleOptionData::forActor($viewer),
        ]);
    }

    /**
     * @return list<AdminRoleRowData>
     */
    private function roleCards(): array
    {
        $roles = Role::query()
            ->with('permissions')
            // One member each, for the avatar on the card. An eager-load limit
            // rather than a whole relation, so a role with two hundred staff
            // costs the same as a role with one.
            ->with(['users' => fn ($query) => $query->orderBy('name')->limit(1)])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return array_values($roles
            ->map(fn (Role $role): AdminRoleRowData => AdminRoleRowData::fromModel($role))
            ->all());
    }

    /**
     * The staff table's filter bar. Lifted from the screen this one replaced.
     *
     * @param  Builder<User>  $query
     */
    private function applyStaffFilters(Builder $query, StaffIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('email'), [$pattern]);
            });
        }

        $query->when(
            $request->validated('role'),
            fn (Builder $q, string $role) => $q->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)),
        );
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

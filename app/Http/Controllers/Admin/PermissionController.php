<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminPermissionRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionIndexRequest;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

/**
 * What the store can be asked to authorise, and who currently holds each of it.
 *
 * Read-only, and not by omission. Permissions are defined in code — the list is
 * {@see PermissionSeeder::PERMISSIONS} and the `can:` middleware on the routes
 * is what enforces them — so a screen that could create one would write a row
 * that nothing ever checks, and a screen that could delete one would silently
 * open every route guarding on it. Roles are where the store makes decisions;
 * this is the reference a role editor works against.
 *
 * Sits behind `roles.manage` rather than `staff.manage`: knowing the exact shape
 * of the authorisation model is a step towards working around it, and the same
 * narrow group that defines what a role means is the group that needs it.
 */
class PermissionController extends Controller
{
    /** Rows per page. The seeded list is short; a custom one need not be. */
    private const PER_PAGE = 25;

    public function __invoke(PermissionIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') ?? 'asc';

        $permissions = Permission::query()
            // Without this the roles column is a query per row.
            ->with('roles:id,name')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/permissions/Index', [
            'permissions' => array_values(array_map(
                static fn (Permission $permission): AdminPermissionRowData => AdminPermissionRowData::fromModel($permission),
                $permissions->items(),
            )),
            'pagination' => PaginationData::fromPaginator($permissions),
            'filters' => [
                'search' => $request->validated('search'),
                'group' => $request->validated('group'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'groups' => $this->groups(),
        ]);
    }

    /**
     * The resource segments present, for the group filter.
     *
     * Derived from the names rather than kept in a lookup table, for the same
     * reason {@see AdminPermissionRowData} derives them: a table would drift
     * from the seeder the first time somebody added a permission.
     *
     * @return list<array{value: string, label: string}>
     */
    private function groups(): array
    {
        $names = Permission::query()->orderBy('name')->pluck('name');

        return array_values($names
            ->map(static fn (mixed $name): string => Str::before((string) $name, '.'))
            ->unique()
            ->sort()
            ->map(static fn (string $group): array => [
                'value' => $group,
                'label' => ucfirst(str_replace('_', ' ', $group)),
            ])
            ->all());
    }

    /**
     * @param  Builder<Permission>  $query
     */
    private function applyFilters(Builder $query, PermissionIndexRequest $request): void
    {
        $query
            ->when(
                $request->validated('search'),
                // Escaped, because a permission name is dotted and a staff
                // member typing `%` should search for a percent sign.
                fn (Builder $q, string $search) => $q->where(
                    'name',
                    'like',
                    '%'.addcslashes($search, '%_\\').'%',
                ),
            )
            ->when(
                $request->validated('group'),
                fn (Builder $q, string $group) => $q->where('name', 'like', $group.'.%'),
            );
    }
}

<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * The permissions reference.
 *
 * Read-only by design, so the first thing pinned here is that there is no way
 * to write one: the only route is a GET, and it sits behind `roles.manage`
 * rather than `staff.manage` — knowing the exact shape of the authorisation
 * model is a step towards working around it.
 *
 * The rest is the filter bar. A permission name is dotted, so the group filter
 * has to match on the segment rather than anywhere in the string, and the
 * search has to treat a typed `%` as a percent sign rather than a wildcard.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);
});

function roleManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Super Admin'));
}

it('lists the permissions with the roles that hold them', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/permissions/Index')
            ->has('permissions')
            ->where('pagination.total', count(PermissionSeeder::PERMISSIONS))
            ->has('groups'),
        );
});

it('splits a dotted name into a label and a resource', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['search' => 'orders.manage']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('permissions', 1)
            ->where('permissions.0.name', 'orders.manage')
            ->where('permissions.0.label', 'Manage')
            ->where('permissions.0.group', 'orders')
            ->where('permissions.0.groupLabel', 'Orders'),
        );
});

it('names Super Admin among the roles holding a permission', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['search' => 'roles.manage']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('permissions', 1)
            ->where('permissions.0.roles', fn (Collection $roles): bool => $roles->contains(PermissionSeeder::SUPER_ADMIN)),
        );
});

it('reports a permission no role holds rather than hiding it', function () {
    Permission::create(['name' => 'reports.view', 'guard_name' => PermissionSeeder::GUARD]);

    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['search' => 'reports.view']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('permissions', 1)
            ->where('permissions.0.roleCount', 0),
        );
});

it('filters by resource on the segment, not anywhere in the name', function () {
    // `staff.manage` contains "manage" but is not in the `orders` resource.
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['group' => 'orders']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissions', fn (Collection $rows): bool => $rows->isNotEmpty()
                && $rows->every(fn (array $row): bool => $row['group'] === 'orders')),
        );
});

it('treats a typed percent sign as text rather than a wildcard', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('permissions', 0));
});

it('refuses a resource filter that is not a permission segment', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['group' => 'orders%']))
        ->assertSessionHasErrors('group');
});

it('refuses a sort column it does not offer', function () {
    $this->actingAs(roleManager())
        ->get(route('admin.permissions.index', ['sort' => 'id']))
        ->assertSessionHasErrors('sort');
});

it('refuses a staff member who may not manage roles', function () {
    $manager = tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));

    $this->actingAs($manager)
        ->get(route('admin.permissions.index'))
        ->assertForbidden();
});

it('refuses a customer outright', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.permissions.index'))
        ->assertForbidden();
});

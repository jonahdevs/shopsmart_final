<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Roles: the screen that decides what every other screen may do.
 *
 * Guarded by `roles.manage`, which the seeder gives to Super Admin alone. The
 * cases worth pinning are the ones where an edit here would reach further than
 * the editor: granting a permission you do not hold, editing the two built-in
 * roles the whole system rests on, and deleting a role somebody is still using.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(PermissionSeeder::SUPER_ADMIN);
});

/** A staff member holding a purpose-built role with only these permissions. */
function roleActorWith(string ...$permissions): User
{
    $role = Role::create([
        'name' => 'Narrow '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('the table lists every role with its permissions and member count', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($this->superAdmin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('admin/roles/Index');

            $roles = collect($page->toArray()['props']['roles']);
            $managerRow = $roles->firstWhere('name', 'Manager');

            expect($managerRow['memberCount'])->toBe(1)
                ->and($managerRow['permissions'])->toContain('orders.manage')
                ->and($managerRow['isProtected'])->toBeFalse()
                ->and($roles->firstWhere('name', 'Admin')['isProtected'])->toBeTrue();
        });
});

test('a role is created with the permissions that were ticked', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), [
            'name' => 'Warehouse',
            'permissions' => ['orders.view', 'products.view'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Warehouse', PermissionSeeder::GUARD);

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['orders.view', 'products.view']);
});

test('a role needs a name that is not already taken', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), ['permissions' => []])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), ['name' => 'Manager'])
        ->assertSessionHasErrors('name');
});

test('a built-in role name cannot be taken by a new role', function () {
    // A second "Admin" would be indistinguishable in every list that shows a
    // role by name, which is how the wrong one ends up being granted.
    foreach (PermissionSeeder::PROTECTED_ROLES as $protected) {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.roles.store'), ['name' => $protected])
            ->assertSessionHasErrors('name');
    }
});

test('a role is renamed and its permissions synced', function () {
    $role = Role::create(['name' => 'Warehouse', 'guard_name' => PermissionSeeder::GUARD]);
    $role->syncPermissions(['orders.view']);

    $this->actingAs($this->superAdmin)
        ->patch(route('admin.roles.update', $role), [
            'name' => 'Fulfilment',
            'permissions' => ['orders.view', 'orders.manage'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role->refresh()->load('permissions');

    expect($role->name)->toBe('Fulfilment')
        ->and($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['orders.view', 'orders.manage']);
});

test('a protected role can be neither edited nor deleted', function () {
    foreach (PermissionSeeder::PROTECTED_ROLES as $protected) {
        $role = Role::findByName($protected, PermissionSeeder::GUARD);

        $this->actingAs($this->superAdmin)->get(route('admin.roles.edit', $role))->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.roles.update', $role), ['name' => 'Renamed', 'permissions' => []])
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        expect(Role::findByName($protected, PermissionSeeder::GUARD)->permissions)->not->toBeEmpty();
    }
});

test('a role with members cannot be deleted', function () {
    // Deleting it would turn each of them into a customer without anybody
    // deciding that.
    $role = Role::create(['name' => 'Warehouse', 'guard_name' => PermissionSeeder::GUARD]);
    $holder = User::factory()->create();
    $holder->assignRole($role);

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHasErrors('name');

    expect(Role::query()->where('name', 'Warehouse')->exists())->toBeTrue()
        ->and($holder->refresh()->isStaff())->toBeTrue();
});

test('an empty role is deleted', function () {
    $role = Role::create(['name' => 'Warehouse', 'guard_name' => PermissionSeeder::GUARD]);

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::query()->where('name', 'Warehouse')->exists())->toBeFalse();
});

test('a role editor cannot grant a permission they do not hold themselves', function () {
    // Otherwise "may define roles" would silently mean "may define a role that
    // grants me everything, then wear it".
    $actor = roleActorWith('roles.manage', 'orders.view');

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), [
            'name' => 'Warehouse',
            'permissions' => ['products.manage'],
        ])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::query()->where('name', 'Warehouse')->exists())->toBeFalse();
});

test('the matrix marks the permissions the editor may not grant', function () {
    $actor = roleActorWith('roles.manage', 'orders.view');

    $this->actingAs($actor)
        ->get(route('admin.roles.create'))
        ->assertInertia(function ($page) {
            $permissions = collect($page->toArray()['props']['groups'])
                ->flatMap(fn (array $group) => $group['permissions'])
                ->keyBy('name');

            expect($permissions['orders.view']['holdable'])->toBeTrue()
                ->and($permissions['products.manage']['holdable'])->toBeFalse();
        });
});

test('a role change takes effect on the holder\'s very next request', function () {
    // Spatie caches the whole role/permission map. A permission removed here
    // that the cache still grants is a hole, not a refresh delay.
    $role = Role::create(['name' => 'Warehouse', 'guard_name' => PermissionSeeder::GUARD]);
    $role->syncPermissions(['orders.view']);

    $holder = User::factory()->create();
    $holder->assignRole($role);

    $this->actingAs($holder)->get(route('admin.orders.index'))->assertOk();

    $this->actingAs($this->superAdmin)
        ->patch(route('admin.roles.update', $role), ['name' => 'Warehouse', 'permissions' => []]);

    $this->actingAs($holder)->get(route('admin.orders.index'))->assertForbidden();
});

test('an Admin holds staff.manage but is refused the roles section entirely', function () {
    // The split that makes the whole section safe: adding colleagues and
    // deciding what a colleague may do are different jobs.
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $role = Role::create(['name' => 'Warehouse', 'guard_name' => PermissionSeeder::GUARD]);

    $this->actingAs($admin)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.roles.update', $role), ['name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect(Role::query()->where('name', 'Warehouse')->exists())->toBeTrue();
});

test('a customer is refused and a guest is sent to log in', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

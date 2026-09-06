<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * The admin panel's door and its locks.
 *
 * Two separate questions are pinned here, because the application answers them
 * with two separate mechanisms: `staff` decides whether someone works here at
 * all, and `can:` decides whether they may reach a given page. A role that is
 * admitted through the door but refused at a page is the case worth proving —
 * it is what makes the permission matrix mean anything.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);
});

/** A staff member holding exactly one named role. */
function staffWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * A staff member holding a purpose-built role with only these permissions.
 *
 * Deliberately a role and not `givePermissionTo()` on the user: staff membership
 * is "holds at least one role" ({@see User::isStaff()}), so a user granted
 * permissions directly is refused at the door by the `staff` middleware before
 * `can:` is ever consulted. Roles are the only way in.
 */
function staffWithPermissions(string ...$permissions): User
{
    $role = Role::create([
        'name' => 'Test role '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('a guest is sent to log in rather than shown the admin panel', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('a customer is refused the admin panel outright', function () {
    // Not a redirect: a customer who reaches /admin typed it, and the honest
    // answer to that is a refusal rather than a friendly bounce.
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('any staff member reaches the overview', function () {
    $this->actingAs(staffWithRole('Support'))
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('signing in sends staff to the admin panel and customers to their account', function () {
    $this->actingAs(staffWithRole('Manager'))
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('account.dashboard'));
});

test('a role without payments.view is refused the payments pages', function () {
    $this->actingAs(staffWithPermissions('orders.view'))
        ->get(route('admin.payments.index'))
        ->assertForbidden();
});

test('a role without orders.manage may read an order but not move it', function () {
    // The whole point of splitting `.view` from `.manage`: a read-only role is
    // admitted to the page and refused at the transition.
    $reader = staffWithPermissions('orders.view');
    $order = Order::factory()->create();

    $this->actingAs($reader)
        ->get(route('admin.orders.show', $order))
        ->assertOk();

    $this->actingAs($reader)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Processing->value,
        ])
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

test('the shared permission list is what the sidebar can render', function () {
    // The sidebar hides links the staff member may not use, and it filters on
    // this prop. If it stopped being shared, every link would vanish for
    // everyone — a silent, total navigation failure — so it is pinned here.
    $this->actingAs(staffWithRole('Support'))
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('auth.isStaff', true)
                ->has('auth.permissions')
                ->whereContains('auth.permissions', 'orders.view')
        );
});

test('a customer is shared no permissions at all', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('auth.isStaff', false)
                ->where('auth.permissions', [])
        );
});

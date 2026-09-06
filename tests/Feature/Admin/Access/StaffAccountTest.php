<?php

use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Staff accounts: who may run the store, and who decides that.
 *
 * The interesting cases here are not the CRUD. They are the four ways
 * `staff.manage` could otherwise become a way to obtain permissions you were
 * never given — granting a role you do not hold, stripping a colleague who
 * outranks you, editing your own roles, and emptying the Super Admin seat — and
 * the fact that no screen in this section ever puts a credential in a prop.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    // Admin holds staff.manage but not roles.manage: the ordinary person who
    // adds colleagues.
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/** A staff member holding a purpose-built role with only these permissions. */
function staffLimitedTo(string ...$permissions): User
{
    $role = Role::create([
        'name' => 'Limited '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function staffHolding(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('the table lists staff and leaves customers out of it', function () {
    $colleague = staffHolding('Support');
    $shopper = User::factory()->create(['name' => 'Wanjiru Kamau']);

    $this->actingAs($this->admin)
        ->get(route('admin.staff.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/staff/Index')
                ->has('staff', 2)
                ->whereNot('staff.0.email', $shopper->email)
                ->whereNot('staff.1.email', $shopper->email)
        );

    expect($colleague->isStaff())->toBeTrue()
        ->and($shopper->isStaff())->toBeFalse();
});

test('a staff row carries no credential material at all', function () {
    // Not a spot check: naming every key means a field added to the Data object
    // without thinking fails this test rather than shipping.
    $colleague = User::factory()->withTwoFactor()->create();
    $colleague->assignRole('Support');

    $this->actingAs($this->admin)
        ->get(route('admin.staff.index', ['search' => $colleague->email]))
        ->assertInertia(
            fn ($page) => $page
                ->has('staff', 1)
                ->has('staff.0', fn ($row) => $row
                    ->hasAll([
                        'id', 'name', 'email', 'roles', 'emailVerifiedAt',
                        'invitationPending', 'createdAt', 'isSelf', 'manageable',
                    ])
                    ->where('email', $colleague->email)
                )
                ->etc()
        );
});

test('the search matches a name and an email', function () {
    $wanted = User::factory()->create(['name' => 'Achieng Otieno', 'email' => 'achieng@example.test']);
    $wanted->assignRole('Support');
    staffHolding('Support');

    foreach (['Achieng', 'achieng@example.test'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.staff.index', ['search' => $term]))
            ->assertInertia(fn ($page) => $page->has('staff', 1)->where('staff.0.email', $wanted->email));
    }
});

test('the role filter narrows the table', function () {
    staffHolding('Support');
    $manager = staffHolding('Manager');

    $this->actingAs($this->admin)
        ->get(route('admin.staff.index', ['role' => 'Manager']))
        ->assertInertia(fn ($page) => $page->has('staff', 1)->where('staff.0.email', $manager->email));
});

test('a sort column outside the whitelist is rejected', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.staff.index', ['sort' => 'password']))
        ->assertSessionHasErrors('sort');
});

test('inviting a colleague creates the account and emails them a password reset link', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), [
            'name' => 'Njeri Mwangi',
            'email' => 'njeri@example.test',
            'roles' => ['Support'],
        ])
        ->assertRedirect(route('admin.staff.index'));

    $invited = User::where('email', 'njeri@example.test')->firstOrFail();

    expect($invited->hasRole('Support'))->toBeTrue()
        ->and($invited->isStaff())->toBeTrue();

    Notification::assertSentTo($invited, ResetPassword::class);
});

test('an invitation cannot set a password from the form', function () {
    Notification::fake();

    // The one credential path: the colleague sets it from the emailed link. A
    // password field posted by an admin — or by anyone who has read the HTML —
    // must not become the account's password.
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), [
            'name' => 'Njeri Mwangi',
            'email' => 'njeri@example.test',
            'roles' => ['Support'],
            'password' => 'chosen-by-the-admin',
        ]);

    $invited = User::where('email', 'njeri@example.test')->firstOrFail();

    expect(Hash::check('chosen-by-the-admin', $invited->password))->toBeFalse();
});

test('an invitation needs a name, an email and at least one role', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'roles']);

    // No role is not "a staff member with nothing to do" — it is a customer.
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), [
            'name' => 'Njeri Mwangi',
            'email' => 'njeri@example.test',
            'roles' => [],
        ])
        ->assertSessionHasErrors('roles');

    expect(User::where('email', 'njeri@example.test')->exists())->toBeFalse();
});

test('a staff member cannot grant a role carrying permissions they do not hold', function () {
    // The whole point of separating staff.manage from the rest: this actor can
    // add colleagues, and can therefore only add colleagues no stronger than
    // themselves.
    $limited = staffLimitedTo('staff.manage');

    $this->actingAs($limited)
        ->post(route('admin.staff.store'), [
            'name' => 'Njeri Mwangi',
            'email' => 'njeri@example.test',
            'roles' => [PermissionSeeder::SUPER_ADMIN],
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::where('email', 'njeri@example.test')->exists())->toBeFalse();
});

test('the invite form offers only the roles the actor may actually grant', function () {
    $limited = staffLimitedTo('staff.manage');

    $this->actingAs($limited)
        ->get(route('admin.staff.create'))
        ->assertInertia(function ($page) {
            $options = collect($page->toArray()['props']['roleOptions']);

            expect($options->firstWhere('name', PermissionSeeder::SUPER_ADMIN)['assignable'])->toBeFalse();
        });
});

test('editing a colleague saves their name, email and roles', function () {
    $colleague = staffHolding('Support');

    $this->actingAs($this->admin)
        ->patch(route('admin.staff.update', $colleague), [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.test',
            'roles' => ['Manager'],
        ])
        ->assertRedirect(route('admin.staff.index'));

    $colleague->refresh();

    expect($colleague->name)->toBe('Renamed Person')
        ->and($colleague->email)->toBe('renamed@example.test')
        ->and($colleague->hasRole('Manager'))->toBeTrue()
        ->and($colleague->hasRole('Support'))->toBeFalse();
});

test('nobody may edit, revoke or reinvite their own staff account', function () {
    // Everything else about your own account lives in Settings. The only thing
    // this form would add is your own roles, and that is the escalation.
    $this->actingAs($this->admin)->get(route('admin.staff.edit', $this->admin))->assertForbidden();

    $this->actingAs($this->admin)
        ->patch(route('admin.staff.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => [PermissionSeeder::SUPER_ADMIN],
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $this->admin))->assertForbidden();

    expect($this->admin->refresh()->hasRole('Admin'))->toBeTrue()
        ->and($this->admin->hasRole(PermissionSeeder::SUPER_ADMIN))->toBeFalse();
});

test('a staff member cannot touch a colleague whose roles they could not have granted', function () {
    // The mirror of escalation. Without this, staff.manage would be a way to
    // lock out the people above you.
    $limited = staffLimitedTo('staff.manage');
    $superior = staffHolding('Admin');

    $this->actingAs($limited)->get(route('admin.staff.edit', $superior))->assertForbidden();
    $this->actingAs($limited)->delete(route('admin.staff.destroy', $superior))->assertForbidden();

    expect($superior->refresh()->hasRole('Admin'))->toBeTrue();
});

test('revoking access takes every role off but keeps the account and its orders', function () {
    $colleague = staffHolding('Support');
    $order = Order::factory()->create(['user_id' => $colleague->getKey()]);

    $this->actingAs($this->admin)
        ->delete(route('admin.staff.destroy', $colleague))
        ->assertRedirect(route('admin.staff.index'));

    $colleague->refresh();

    expect($colleague->exists)->toBeTrue()
        ->and($colleague->isCustomer())->toBeTrue()
        ->and($colleague->roles)->toBeEmpty()
        ->and($order->fresh()->user_id)->toBe($colleague->getKey());
});

test('a revoked colleague is refused the admin panel on their very next request', function () {
    // Spatie caches the whole permission map. A demotion that only takes effect
    // after the cache expires is not a demotion.
    $colleague = staffHolding('Manager');

    $this->actingAs($colleague)->get(route('admin.orders.index'))->assertOk();

    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $colleague));

    $this->actingAs($colleague)->get(route('admin.orders.index'))->assertForbidden();
});

test('the last Super Admin cannot be demoted', function () {
    // The only person able to act on a Super Admin is somebody holding every
    // permission themselves — here through a separate role, which is exactly
    // the case where the seat could be emptied by accident.
    $lastSuperAdmin = staffHolding(PermissionSeeder::SUPER_ADMIN);
    $actor = staffLimitedTo(...PermissionSeeder::PERMISSIONS);

    $this->actingAs($actor)
        ->patch(route('admin.staff.update', $lastSuperAdmin), [
            'name' => $lastSuperAdmin->name,
            'email' => $lastSuperAdmin->email,
            'roles' => ['Manager'],
        ])
        ->assertSessionHasErrors('roles');

    expect($lastSuperAdmin->refresh()->hasRole(PermissionSeeder::SUPER_ADMIN))->toBeTrue();
});

test('the last Super Admin cannot have their access revoked either', function () {
    $lastSuperAdmin = staffHolding(PermissionSeeder::SUPER_ADMIN);
    $actor = staffLimitedTo(...PermissionSeeder::PERMISSIONS);

    $this->actingAs($actor)
        ->delete(route('admin.staff.destroy', $lastSuperAdmin))
        ->assertSessionHasErrors('roles');

    expect($lastSuperAdmin->refresh()->hasRole(PermissionSeeder::SUPER_ADMIN))->toBeTrue();
});

test('a Super Admin who is not the last one may be demoted', function () {
    // The rule is about the seat, not the person: with a colleague still
    // holding it, the change goes through.
    $superAdmin = staffHolding(PermissionSeeder::SUPER_ADMIN);
    staffHolding(PermissionSeeder::SUPER_ADMIN);
    $actor = staffLimitedTo(...PermissionSeeder::PERMISSIONS);

    $this->actingAs($actor)
        ->patch(route('admin.staff.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'roles' => ['Manager'],
        ])
        ->assertRedirect(route('admin.staff.index'));

    expect($superAdmin->refresh()->hasRole(PermissionSeeder::SUPER_ADMIN))->toBeFalse();
});

test('an Admin cannot demote a Super Admin at all', function () {
    // A different mechanism from the last-one rule: Super Admin carries
    // roles.manage, which an Admin does not hold, so the role is not theirs to
    // take away from anybody.
    $superAdmin = staffHolding(PermissionSeeder::SUPER_ADMIN);
    staffHolding(PermissionSeeder::SUPER_ADMIN);

    $this->actingAs($this->admin)
        ->patch(route('admin.staff.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'roles' => ['Manager'],
        ])
        ->assertForbidden();

    expect($superAdmin->refresh()->hasRole(PermissionSeeder::SUPER_ADMIN))->toBeTrue();
});

test('a staff member without staff.manage is refused the section', function () {
    $support = staffHolding('Support');

    $this->actingAs($support)->get(route('admin.staff.index'))->assertForbidden();
    $this->actingAs($support)->get(route('admin.staff.create'))->assertForbidden();
    $this->actingAs($support)
        ->post(route('admin.staff.store'), [
            'name' => 'Njeri Mwangi',
            'email' => 'njeri@example.test',
            'roles' => ['Support'],
        ])
        ->assertForbidden();
});

test('a customer is refused and a guest is sent to log in', function () {
    $this->get(route('admin.staff.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.staff.index'))
        ->assertForbidden();
});

test('a customer id is a 404 rather than a 403 on the staff routes', function () {
    // There is no staff account at that id. Answering "yes, but not for you"
    // about a shopper's row tells the asker something they did not have.
    $shopper = User::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.staff.edit', $shopper))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $shopper))->assertNotFound();
});

test('the invitation can be sent again without changing the account', function () {
    Notification::fake();

    $colleague = staffHolding('Support');

    $this->actingAs($this->admin)
        ->post(route('admin.staff.invitation', $colleague))
        ->assertRedirect();

    Notification::assertSentTo($colleague, ResetPassword::class);

    expect($colleague->refresh()->hasRole('Support'))->toBeTrue();
});

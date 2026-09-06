<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/**
 * The admin customers section: who may reach it, what it counts, and — the part
 * worth guarding hardest — what it refuses to show.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');
});

/**
 * A staff member holding every permission except the ones named.
 *
 * No seeded role lacks `customers.view`, so a role has to be built to prove the
 * `can:` middleware is what refuses the request rather than the `staff` gate.
 */
function staffWithoutCustomerPermission(string ...$withheld): User
{
    $role = Role::create([
        'name' => 'Restricted '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions(array_values(array_diff(PermissionSeeder::PERMISSIONS, $withheld)));

    $staff = User::factory()->create();
    $staff->assignRole($role);

    return $staff;
}

describe('index', function () {
    test('it lists customers with their order count and paid lifetime spend', function () {
        $customer = User::factory()->create(['name' => 'Amina Wanjiru']);

        Order::factory()->paid()->create(['user_id' => $customer->id, 'total_cents' => 120_000]);
        Order::factory()->paid()->create(['user_id' => $customer->id, 'total_cents' => 80_000]);
        // Placed but never paid: it counts as an order and not as spend.
        Order::factory()->create(['user_id' => $customer->id, 'total_cents' => 999_000]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/customers/Index')
                ->has('customers', 1)
                ->where('customers.0.name', 'Amina Wanjiru')
                ->where('customers.0.orderCount', 3)
                ->where('customers.0.lifetimeSpentCents', 200_000)
                ->where('customers.0.lifetimeSpentFormatted', money(200_000)));
    });

    test('it excludes staff accounts from the customer list', function () {
        User::factory()->create(['name' => 'A Shopper']);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers', 1)
                ->where('customers.0.name', 'A Shopper'));
    });

    /**
     * Deleting an account nulls `orders.user_id` and leaves the frozen
     * `customer_name` / `customer_email` standing so the sale stays auditable.
     * The customers table is built from `users`, so a closed account has no row
     * — matching those snapshots back to a person would undo the deletion the
     * customer asked for.
     */
    test('it does not resurrect a customer who deleted their account', function () {
        Order::factory()->paid()->create([
            'user_id' => null,
            'customer_name' => 'Amina Wanjiru',
            'customer_email' => 'amina@example.test',
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['search' => 'amina@example.test']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 0));
    });

    test('it does not attach an orphaned order to a customer who shares its email', function () {
        $customer = User::factory()->create(['email' => 'amina@example.test']);

        Order::factory()->paid()->create([
            'user_id' => null,
            'customer_email' => 'amina@example.test',
            'total_cents' => 900_000,
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('detail.orders', 0)
                ->where('detail.customer.lifetimeSpentCents', 0));
    });

    test('it treats a typed percent sign as a literal rather than a wildcard', function () {
        $literal = User::factory()->create(['name' => 'Fifty% Off Fan']);
        User::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['search' => '%']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers', 1)
                ->where('customers.0.id', $literal->id));
    });

    test('it treats a typed underscore as a literal rather than a single-character wildcard', function () {
        $literal = User::factory()->create(['name' => 'snake_case Shopper']);
        User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['search' => 'e_c']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers', 1)
                ->where('customers.0.id', $literal->id));
    });

    test('it sorts by lifetime spend when asked', function () {
        $small = User::factory()->create();
        $large = User::factory()->create();

        Order::factory()->paid()->create(['user_id' => $small->id, 'total_cents' => 10_000]);
        Order::factory()->paid()->create(['user_id' => $large->id, 'total_cents' => 900_000]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['sort' => 'lifetime_spent_cents', 'direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customers.0.id', $large->id)
                ->where('customers.1.id', $small->id));
    });

    test('it rejects a sort column that is not on the allow list', function () {
        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['sort' => 'password']))
            ->assertSessionHasErrors('sort');
    });

    test('it returns 403 for a staff member without customers.view', function () {
        $this->actingAs(staffWithoutCustomerPermission('customers.view', 'customers.manage'))
            ->get(route('admin.customers.index'))
            ->assertForbidden();
    });

    test('it returns 403 for a customer', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.customers.index'))
            ->assertForbidden();
    });

    test('it redirects a guest to sign in', function () {
        $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
    });
});

describe('show', function () {
    test('it renders the orders, addresses and reviews belonging to the customer', function () {
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id, 'total_cents' => 250_000]);
        Address::factory()->isDefault()->create(['user_id' => $customer->id]);
        Review::factory()->approved()->create(['user_id' => $customer->id]);

        // Another customer's records must not leak onto this page.
        $stranger = User::factory()->create();
        Order::factory()->create(['user_id' => $stranger->id]);
        Address::factory()->create(['user_id' => $stranger->id]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/customers/Show')
                ->has('detail.orders', 1)
                ->where('detail.orders.0.orderNumber', $order->order_number)
                ->has('detail.addresses', 1)
                ->has('detail.reviews', 1)
                ->where('detail.reviewCount', 1)
                ->where('detail.paidOrderCount', 1)
                ->where('detail.customer.lifetimeSpentCents', 250_000)
                ->where('detail.averageOrderValueCents', 250_000));
    });

    test('it never exposes a payment payload or any credential column', function () {
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);

        // Distinctive sentinels: the factories generate random names and phone
        // numbers, and a value that could plausibly appear by chance would make
        // this assertion mean nothing.
        Payment::factory()->successful()->create([
            'order_id' => $order->id,
            'payload' => [
                'payer' => 'PAYER-SENTINEL-NAME',
                'phone' => 'PAYER-SENTINEL-PHONE',
                'card' => 'PAYER-SENTINEL-CARD',
            ],
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertDontSee('PAYER-SENTINEL-NAME')
            ->assertDontSee('PAYER-SENTINEL-PHONE')
            ->assertDontSee('PAYER-SENTINEL-CARD')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('detail.customer.password')
                ->missing('detail.customer.two_factor_secret')
                ->missing('detail.customer.remember_token')
                ->missing('detail.payments')
                ->missing('detail.orders.0.payments'));
    });

    test('it returns 404 for a staff account', function () {
        $colleague = User::factory()->create();
        $colleague->assignRole('Support');

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $colleague))
            ->assertNotFound();
    });

    test('it returns 403 for a staff member without customers.view', function () {
        $this->actingAs(staffWithoutCustomerPermission('customers.view', 'customers.manage'))
            ->get(route('admin.customers.show', User::factory()->create()))
            ->assertForbidden();
    });
});

describe('update', function () {
    test('it corrects the display name', function () {
        $customer = User::factory()->create(['name' => 'Amina Wanjru']);

        $this->actingAs($this->manager)
            ->patch(route('admin.customers.update', $customer), ['name' => 'Amina Wanjiru'])
            ->assertRedirect();

        expect($customer->refresh()->name)->toBe('Amina Wanjiru');
    });

    test('it rejects a blank name with a message', function () {
        $customer = User::factory()->create(['name' => 'Amina Wanjiru']);

        $this->actingAs($this->manager)
            ->patch(route('admin.customers.update', $customer), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        expect($customer->refresh()->name)->toBe('Amina Wanjiru');
    });

    test('it ignores an email address smuggled into the payload', function () {
        $customer = User::factory()->create(['email' => 'amina@example.test']);

        $this->actingAs($this->manager)
            ->patch(route('admin.customers.update', $customer), [
                'name' => 'Amina Wanjiru',
                'email' => 'attacker@example.test',
            ])
            ->assertRedirect();

        expect($customer->refresh()->email)->toBe('amina@example.test');
    });

    test('it returns 403 for a staff member holding only customers.view', function () {
        $support = staffWithoutCustomerPermission('customers.manage');

        $this->actingAs($support)
            ->patch(route('admin.customers.update', User::factory()->create()), ['name' => 'New Name'])
            ->assertForbidden();
    });

    test('it redirects a guest to sign in', function () {
        $this->patch(route('admin.customers.update', User::factory()->create()), ['name' => 'New Name'])
            ->assertRedirect(route('login'));
    });
});

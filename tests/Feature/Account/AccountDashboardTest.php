<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecentlyViewed;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * The first screen a customer sees after signing in.
 *
 * Two things are being pinned here: that `dashboard` forks by role rather than
 * showing a customer the staff page, and that the account dashboard shows this
 * customer's rows and nobody else's.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 6 page components are built by another agent from the props
    // asserted here; what is under test is the contract, not the Vue module.
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->customer = User::factory()->create();
});

test('a customer landing on the dashboard is sent to their account', function () {
    $this->actingAs($this->customer)
        ->get(route('dashboard'))
        ->assertRedirect(route('account.dashboard'));
});

test('a staff member is sent to the admin panel, not the account area', function () {
    $this->seed(PermissionSeeder::class);

    $staff = User::factory()->create();
    $staff->assignRole('Support');

    // Phase 7 replaced the staff placeholder with the real admin overview, so
    // this is now a redirect on both sides of the fork rather than a page.
    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('a guest is sent to sign in rather than to the account', function () {
    $this->get(route('account.dashboard'))->assertRedirect(route('login'));
});

test('staff are turned away from the customer account area', function () {
    $this->seed(PermissionSeeder::class);

    $staff = User::factory()->create();
    $staff->assignRole('Support');

    $this->actingAs($staff)
        ->get(route('account.dashboard'))
        ->assertRedirect(route('cart.index'));
});

test('the dashboard counts and lists only this customer rows', function () {
    $stranger = User::factory()->create();

    Order::factory()->count(2)->create(['user_id' => $this->customer->id]);
    Order::factory()->count(3)->create(['user_id' => $stranger->id]);

    Address::factory()->create(['user_id' => $this->customer->id]);
    Address::factory()->count(4)->create(['user_id' => $stranger->id]);

    $this->actingAs($this->customer)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('account/Dashboard')
            ->where('customerName', $this->customer->name)
            ->where('stats.orderCount', 2)
            ->where('stats.addressCount', 1)
            ->where('stats.wishlistCount', 0)
            ->where('stats.awaitingReviewCount', 0)
            ->has('recentOrders', 2)
            ->has('defaultAddress')
            ->has('breadcrumbs', 2));
});

test('the dashboard shows at most the three newest orders', function () {
    $newest = Order::factory()->create([
        'user_id' => $this->customer->id,
        'placed_at' => now()->subHour(),
    ]);

    Order::factory()->count(4)->create([
        'user_id' => $this->customer->id,
        'placed_at' => now()->subWeek(),
    ]);

    $this->actingAs($this->customer)
        ->get(route('account.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.orderCount', 5)
            ->has('recentOrders', 3)
            ->where('recentOrders.0.orderNumber', $newest->order_number));
});

test('the dashboard defers its recently viewed rail to this customer own history', function () {
    $seen = Product::factory()->published()->create();
    $strangersProduct = Product::factory()->published()->create();

    RecentlyViewed::record($this->customer, $seen);
    RecentlyViewed::record(User::factory()->create(), $strangersProduct);

    $this->actingAs($this->customer)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('recentlyViewed')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('recentlyViewed', 1)
                ->where('recentlyViewed.0.id', $seen->id)));
});

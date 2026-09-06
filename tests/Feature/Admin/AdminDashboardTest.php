<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Settings\InventorySettings;
use Database\Seeders\PermissionSeeder;

/**
 * The overview's arithmetic.
 *
 * Every tile is a decision about which rows count, and the one that matters
 * most is that revenue counts paid orders only — a dashboard that reports
 * uncollected money as income is worse than no dashboard.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('revenue counts paid orders and ignores unpaid ones', function () {
    Order::factory()->paid()->create([
        'total_cents' => 250_000,
        'paid_at' => now()->subDays(2),
    ]);
    Order::factory()->create(['total_cents' => 999_999]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/Dashboard')
                ->where('stats.revenueCents', 250_000)
                ->where('stats.paidOrderCount', 1)
        );
});

test('revenue is measured on when the money arrived, not when the order was placed', function () {
    // Placed long ago, collected inside the window: that is revenue for the
    // period the payment settled in.
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'placed_at' => now()->subMonths(6),
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.revenueCents', 100_000));
});

test('a paid order outside the window is left out of the current period', function () {
    Order::factory()->paid()->create([
        'total_cents' => 400_000,
        'paid_at' => now()->subDays(45),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.revenueCents', 0));
});

test('the average order value divides revenue by paid orders', function () {
    Order::factory()->paid()->create(['total_cents' => 100_000, 'paid_at' => now()]);
    Order::factory()->paid()->create(['total_cents' => 300_000, 'paid_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page->where('stats.averageOrderValueCents', 200_000)
        );
});

test('a store with no sales reports zero rather than dividing by zero', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.revenueCents', 0)
                ->where('stats.averageOrderValueCents', 0)
                ->where('stats.revenueChangePercent', null)
        );
});

test('a period with no baseline shows no change rather than a made-up percentage', function () {
    Order::factory()->paid()->create(['total_cents' => 100_000, 'paid_at' => now()]);

    // Nothing in the previous window: "+100%" would be arithmetic dressed up as
    // insight, so the tile shows nothing at all.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.revenueChangePercent', null));
});

test('the change percentage compares the window against the one before it', function () {
    Order::factory()->paid()->create([
        'total_cents' => 200_000,
        'paid_at' => now()->subDays(45),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 300_000,
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        // 50, not 50.0: a whole-number float serialises to JSON without its
        // decimal, and the assertion compares identity.
        ->assertInertia(fn ($page) => $page->where('stats.revenueChangePercent', 50));
});

test('the queues count orders awaiting payment and awaiting fulfilment', function () {
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->paid()->create(['status' => OrderStatus::Processing]);
    Order::factory()->cancelled()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                // The cancelled order is not awaiting anything.
                ->where('stats.awaitingPaymentCount', 1)
                ->where('stats.awaitingFulfilmentCount', 1)
        );
});

test('only stock-tracked products can be counted as low', function () {
    $threshold = app(InventorySettings::class)->low_stock_threshold;

    Product::factory()->create(['stock_quantity' => $threshold]);
    Product::factory()->create(['stock_quantity' => $threshold + 50]);
    // Untracked stock is never low, however the threshold moves.
    Product::factory()->create(['stock_quantity' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.lowStockCount', 1));
});

test('new customers counts shoppers and not staff', function () {
    User::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(
            // The signed-in Admin holds a role, so they are staff and excluded.
            fn ($page) => $page->where('stats.newCustomerCount', 2)
        );
});

test('the recent orders list carries the newest orders', function () {
    Order::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->has('recentOrders', 3));
});

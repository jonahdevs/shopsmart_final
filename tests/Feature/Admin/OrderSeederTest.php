<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\OrderSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * The demo store's books have to balance.
 *
 * This seeder exists so the admin can be looked at, which means its output is
 * read by a human comparing figures across screens. An order whose totals do
 * not reconcile, or a cancelled order carrying `paid_at`, makes the application
 * look broken when the fault is in the fixture — and that is a hard bug to
 * chase, because every screen reports it faithfully.
 *
 * These are invariants, not counts: the seeder is free to change how much it
 * makes and none of these assertions care.
 *
 * Grouped into three tests rather than eight on purpose. Seeding is the
 * expensive part and `beforeEach` pays it per test, so each extra test costs
 * another full run of the seeder for no extra coverage.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    // The seeder sells whatever published, priced stock it can find; giving it
    // factory products keeps this test off the image-heavy ProductSeeder.
    Product::factory()->count(12)->published()->create(['price' => 250_000]);

    $this->seed(UserSeeder::class);
    $this->seed(OrderSeeder::class);
});

test('every order total reconciles with its own lines', function () {
    $orders = Order::query()->with('items')->get();

    expect($orders)->not->toBeEmpty();

    foreach ($orders as $order) {
        expect($order->items->sum('subtotal_cents'))
            ->toBe($order->subtotal_cents, "Order {$order->order_number} subtotal")
            ->and($order->subtotal_cents - $order->discount_cents + $order->shipping_cents)
            ->toBe($order->total_cents, "Order {$order->order_number} total");
    }
});

test('money is recorded against exactly the orders that collected it', function () {
    $orders = Order::query()->get();

    $unpaid = $orders->whereIn('status', [OrderStatus::Pending, OrderStatus::Cancelled]);
    $settled = $orders->whereIn('status', [
        OrderStatus::Processing,
        OrderStatus::OutForDelivery,
        OrderStatus::Completed,
    ]);

    expect($unpaid)->not->toBeEmpty()
        ->and($settled)->not->toBeEmpty();

    foreach ($unpaid as $order) {
        // A cancelled order carrying paid_at would inflate revenue on every
        // screen that reports it, and each screen would be individually right.
        expect($order->paid_at)->toBeNull()
            ->and($order->payment_status)->not->toBe(PaymentStatus::Success);
    }

    foreach ($settled as $order) {
        expect($order->payment_status)->toBe(PaymentStatus::Success)
            ->and($order->paid_at)->not->toBeNull()
            ->and($order->payment_method)->not->toBeNull()
            // Revenue is measured on paid_at, so it must never precede the sale.
            ->and($order->paid_at->gte($order->placed_at))->toBeTrue();
    }
});

test('the history has a shape the dashboard can plot', function () {
    $tradingDays = Order::query()
        ->selectRaw('DATE(placed_at) as day')
        ->distinct()
        ->count();

    // Every trend on the dashboard compares a period against the one before
    // it. Orders sharing one timestamp draw a flat line and a null delta.
    expect($tradingDays)->toBeGreaterThan(30);

    foreach (Order::query()->with('user')->get() as $order) {
        expect($order->placed_at->gte($order->user->created_at))->toBeTrue(
            "Order {$order->order_number} predates its customer"
        );
    }

    // One login per role, so the permission-filtered sidebar can be shown
    // rather than described.
    foreach (['Super Admin', 'Admin', 'Manager', 'Support'] as $role) {
        expect(User::query()->role($role)->exists())->toBeTrue("Missing a {$role} login");
    }
});

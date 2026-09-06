<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * The orders table and the one page staff act on an order from.
 *
 * The filters are the interesting part: each one is a decision the controller
 * makes about a query string, and `sort` is the one place on this page where a
 * query string would otherwise reach `orderBy`.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');
});

test('the table lists orders newest first', function () {
    $older = Order::factory()->create(['placed_at' => now()->subWeek()]);
    $newer = Order::factory()->create(['placed_at' => now()]);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/orders/Index')
                ->has('orders', 2)
                ->where('orders.0.orderNumber', $newer->order_number)
                ->where('orders.1.orderNumber', $older->order_number)
        );
});

test('the item count comes from the line quantities, not the number of lines', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 3]);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index'))
        ->assertInertia(fn ($page) => $page->where('orders.0.itemCount', 5));
});

test('the search matches an order number, a customer name and an email', function () {
    $wanted = Order::factory()->create([
        'customer_name' => 'Wanjiru Kamau',
        'customer_email' => 'wanjiru@example.test',
    ]);
    Order::factory()->create([
        'customer_name' => 'Someone Else',
        'customer_email' => 'other@example.test',
    ]);

    foreach ([$wanted->order_number, 'Wanjiru', 'wanjiru@example.test'] as $term) {
        $this->actingAs($this->manager)
            ->get(route('admin.orders.index', ['search' => $term]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('orders', 1)
                    ->where('orders.0.orderNumber', $wanted->order_number)
            );
    }
});

test('a typed wildcard is searched for literally rather than matching everything', function () {
    // The pattern is bound, so this was never an injection risk — it was a
    // correctness one: an unescaped `%` matched the whole table.
    Order::factory()->create(['customer_name' => 'Plain Name']);
    Order::factory()->create(['customer_name' => '100% Cotton Buyer']);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', ['search' => '100%']))
        ->assertInertia(
            fn ($page) => $page
                ->has('orders', 1)
                ->where('orders.0.customerName', '100% Cotton Buyer')
        );

    // A bare `%` finds the row that literally contains one, and leaves the
    // other alone. Unescaped it would have returned the whole table.
    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', ['search' => '%']))
        ->assertInertia(
            fn ($page) => $page
                ->has('orders', 1)
                ->where('orders.0.customerName', '100% Cotton Buyer')
        );
});

test('the table filters by status and by payment status', function () {
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->paid()->create();

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', ['status' => OrderStatus::Pending->value]))
        ->assertInertia(fn ($page) => $page->has('orders', 1));

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', [
            'payment_status' => PaymentStatus::Success->value,
        ]))
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

test('a sort column outside the whitelist is rejected before it reaches the query', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', ['sort' => 'customer_email']))
        ->assertSessionHasErrors('sort');

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', ['sort' => 'total_cents']))
        ->assertOk();
});

test('a date range narrows the table to orders placed inside it', function () {
    Order::factory()->create(['placed_at' => now()->subMonth()]);
    Order::factory()->create(['placed_at' => now()]);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', [
            'from' => now()->subWeek()->toDateString(),
        ]))
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

test('a range whose end precedes its start is refused', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.orders.index', [
            'from' => now()->toDateString(),
            'to' => now()->subWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors('to');
});

test('the detail page carries the staff note and the collection attempts', function () {
    $order = Order::factory()->create(['staff_note' => 'Call before delivery.']);
    Payment::factory()->for($order)->create();

    $this->actingAs($this->manager)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/orders/Show')
                ->where('detail.staffNote', 'Call before delivery.')
                ->has('detail.payments', 1)
                ->has('detail.availableStatuses')
        );
});

test('the encrypted gateway payload never reaches the page', function () {
    $order = Order::factory()->create();
    Payment::factory()->for($order)->create([
        'payload' => ['customer' => ['phone' => '+254700000000']],
    ]);

    $response = $this->actingAs($this->manager)
        ->get(route('admin.orders.show', $order));

    // The payload holds the payer's name, phone and masked instrument. It is
    // encrypted at rest for that reason and must not be undone by a JSON prop.
    $response->assertOk();
    expect($response->getContent())->not->toContain('+254700000000');
});

test('a final order offers no further transitions', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Refunded]);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('detail.availableStatuses', []));
});

test('staff can move an order and the customer is told once', function () {
    Notification::fake();

    $order = Order::factory()->paid()->create();

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::OutForDelivery->value,
        ])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery);

    Notification::assertSentToTimes(
        $order->user,
        OrderStatusChanged::class,
        1,
    );
});

test('moving an order to the status it already holds is refused, not silently repeated', function () {
    Notification::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Pending->value,
        ])
        ->assertSessionHasErrors('status');

    Notification::assertNothingSent();
});

test('an unknown status is rejected by validation', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), ['status' => 'teleported'])
        ->assertSessionHasErrors('status');
});

test('the staff note saves and is never shown as the customer note', function () {
    $order = Order::factory()->create(['customer_note' => 'Leave at reception.']);

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.note', $order), [
            'staff_note' => 'Flagged for a follow-up call.',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->staff_note)->toBe('Flagged for a follow-up call.')
        ->and($order->customer_note)->toBe('Leave at reception.');
});

test('an over-long staff note is refused', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.note', $order), [
            'staff_note' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('staff_note');
});

test('an order is addressed by its number, never by its primary key', function () {
    $order = Order::factory()->create();

    // The URL is built from the route key; asking for the id must not resolve.
    $this->actingAs($this->manager)
        ->get('/admin/orders/'.$order->getKey())
        ->assertNotFound();
});

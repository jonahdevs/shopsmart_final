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
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

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

test('re-saving the status an order already holds succeeds without telling anyone', function () {
    Notification::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    // The picker opens on the current status, so an untouched form reaches this
    // route routinely. Nothing changed, so nothing is emailed — but nothing is
    // wrong either, and an error here would train staff to ignore the field.
    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Pending->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);

    Notification::assertNothingSent();
});

test('every move the lifecycle allows is accepted', function (OrderStatus $from, OrderStatus $to) {
    Notification::fake();

    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), ['status' => $to->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($order->refresh()->status)->toBe($to);
})->with([
    'taken to being packed' => [OrderStatus::Pending, OrderStatus::Processing],
    'taken to called off' => [OrderStatus::Pending, OrderStatus::Cancelled],
    'packed to on the road' => [OrderStatus::Processing, OrderStatus::OutForDelivery],
    'packed to handed over' => [OrderStatus::Processing, OrderStatus::Completed],
    'packed to called off' => [OrderStatus::Processing, OrderStatus::Cancelled],
    'on the road to signed for' => [OrderStatus::OutForDelivery, OrderStatus::Completed],
    'on the road to called off' => [OrderStatus::OutForDelivery, OrderStatus::Cancelled],
    'signed for to money returned' => [OrderStatus::Completed, OrderStatus::Refunded],
]);

test('a move the lifecycle forbids is refused and the order stays where it was', function (OrderStatus $from, OrderStatus $to) {
    Notification::fake();

    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), ['status' => $to->value])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe($from);

    Notification::assertNothingSent();
})->with([
    'a cancelled order cannot be reopened' => [OrderStatus::Cancelled, OrderStatus::Pending],
    'a refunded order cannot be put back to work' => [OrderStatus::Refunded, OrderStatus::Processing],
    'a delivered order cannot go backwards' => [OrderStatus::Completed, OrderStatus::Processing],
    'a delivered order is too late to cancel' => [OrderStatus::Completed, OrderStatus::Cancelled],
    'an unpacked order cannot skip onto the road' => [OrderStatus::Pending, OrderStatus::OutForDelivery],
    'nothing is refunded before it is delivered' => [OrderStatus::Processing, OrderStatus::Refunded],
]);

test('the refusal names both the status the order is in and the one that was asked for', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs($this->manager)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Pending->value,
        ])
        ->assertSessionHasErrors([
            'status' => 'An order that is cancelled cannot be moved to pending.',
        ]);
});

test('the detail page offers exactly the moves the order can still make', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.show', $order))
        ->assertInertia(
            fn ($page) => $page
                // The current status is not among them — the page renders that
                // itself, as the selected option. These are the moves only.
                ->has('detail.availableStatuses', 3)
                ->where('detail.availableStatuses.0.value', OrderStatus::OutForDelivery->value)
                ->where('detail.availableStatuses.1.value', OrderStatus::Completed->value)
                ->where('detail.availableStatuses.2.value', OrderStatus::Cancelled->value)
        );
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

test('staff get the order as a pdf built from the customer receipt template', function () {
    // The render itself is faked — a real one boots headless Chrome. What
    // matters here is that staff reach the SAME view the customer downloads,
    // so the two documents cannot drift apart.
    $pdf = Pdf::fake();

    $order = Order::factory()->create(['customer_name' => 'Amina Wanjiru']);
    OrderItem::factory()->for($order)->create(['name' => 'Ridgeline Drill']);

    $this->actingAs($this->manager)
        ->get(route('admin.orders.invoice', $order))
        ->assertOk();

    $pdf->assertRespondedWithPdf(function (PdfBuilder $built) use ($order): bool {
        $html = $built->getHtml();

        return $built->viewName === 'pdf.receipt'
            && $built->downloadName === "invoice-{$order->order_number}.pdf"
            && str_contains($html, $order->order_number)
            && str_contains($html, 'Amina Wanjiru')
            && str_contains($html, 'Ridgeline Drill');
    });
});

test('the customer whose order it is cannot pull the staff copy', function () {
    Pdf::fake();

    $customer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $customer->id]);

    // Their own receipt is at orders.receipt. This route is the admin panel,
    // and owning the order is not a way into it.
    $this->actingAs($customer)
        ->get(route('admin.orders.invoice', $order))
        ->assertForbidden();
});

test('an order is addressed by its number, never by its primary key', function () {
    $order = Order::factory()->create();

    // The URL is built from the route key; asking for the id must not resolve.
    $this->actingAs($this->manager)
        ->get('/admin/orders/'.$order->getKey())
        ->assertNotFound();
});

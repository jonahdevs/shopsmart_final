<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * The reconciliation screens.
 *
 * These pages are read-only by design — a payment row is the record of what a
 * gateway did — so the tests that matter most are the ones proving there is no
 * write route, and that the encrypted payload stays encrypted.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');
});

test('the table lists collection attempts newest first', function () {
    $older = Payment::factory()->create(['created_at' => now()->subDay()]);
    $newer = Payment::factory()->create(['created_at' => now()]);

    $this->actingAs($this->manager)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/payments/Index')
                ->has('payments', 2)
                ->where('payments.0.reference', $newer->reference)
                ->where('payments.1.reference', $older->reference)
        );
});

test('the search matches a reference and the order number it belongs to', function () {
    $order = Order::factory()->create();
    $wanted = Payment::factory()->for($order)->create();
    Payment::factory()->create();

    foreach ([$wanted->reference, $order->order_number] as $term) {
        $this->actingAs($this->manager)
            ->get(route('admin.payments.index', ['search' => $term]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('payments', 1)
                    ->where('payments.0.reference', $wanted->reference)
            );
    }
});

test('the table filters by status', function () {
    Payment::factory()->successful()->create();
    Payment::factory()->failed()->create();

    $this->actingAs($this->manager)
        ->get(route('admin.payments.index', [
            'status' => PaymentStatus::Success->value,
        ]))
        ->assertInertia(fn ($page) => $page->has('payments', 1));
});

test('the gateway filter offers only gateways that have actually been used', function () {
    Payment::factory()->create(['gateway' => 'paystack']);

    $this->actingAs($this->manager)
        ->get(route('admin.payments.index'))
        ->assertInertia(fn ($page) => $page->where('gateways', ['paystack']));
});

test('a sort column outside the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.payments.index', ['sort' => 'reference']))
        ->assertSessionHasErrors('sort');
});

test('the detail page shows the attempt without its gateway payload', function () {
    $payment = Payment::factory()->create([
        'payload' => ['customer' => ['phone' => '+254700111222']],
        'failure_reason' => 'Insufficient funds',
    ]);

    $response = $this->actingAs($this->manager)
        ->get(route('admin.payments.show', $payment));

    $response->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('admin/payments/Show')
            ->where('payment.reference', $payment->reference)
            ->where('payment.failureReason', 'Insufficient funds')
            ->missing('payment.payload')
    );

    expect($response->getContent())->not->toContain('+254700111222');
});

test('the payments screens expose no way to write to a payment', function () {
    $payment = Payment::factory()->create();

    // Settlement happens through the gateway service and Order::markPaid();
    // staff editing this record would destroy the only account of what the
    // gateway actually did.
    foreach (['post', 'patch', 'put', 'delete'] as $method) {
        $this->actingAs($this->manager)
            ->{$method}(route('admin.payments.show', $payment))
            ->assertMethodNotAllowed();
    }
});

test('the amount shown is the frozen one, not the live order total', function () {
    $order = Order::factory()->create(['total_cents' => 500_000]);
    $payment = Payment::factory()->for($order)->create(['amount_cents' => 300_000]);

    // The frozen amount is what a verification is checked against; that is what
    // stops an order edit between initiation and settlement being exploitable.
    $order->update(['total_cents' => 900_000]);

    $this->actingAs($this->manager)
        ->get(route('admin.payments.show', $payment))
        ->assertInertia(fn ($page) => $page->where('payment.amountCents', 300_000));
});

<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia;

/**
 * The pay-for-an-order page and the two calls the popup makes against it.
 *
 * Paying is its own repeatable step, so every action here is scoped to the
 * shopper who owns the order — someone else's order must not even confirm that
 * it exists. `start` answers JSON because the popup is opened from JavaScript
 * and the page must survive it; `verify` takes a reference from the browser and
 * believes nothing about it except that Paystack has to be asked.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // shop/Payment is not written yet; what is under test is the prop contract
    // the controller publishes, not the existence of a Vue module.
    config()->set('inertia.testing.ensure_pages_exist', false);

    Http::preventStrayRequests();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->bank_transfer_enabled = true;
    $payments->bank_details = 'Equity Bank, account 0123456789';
    $payments->save();

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_test_page';
    $api->save();

    $this->customer = User::factory()->create();
});

test('the payment page renders for the shopper who owns the order', function () {
    $product = Product::factory()->published()->create(['name' => 'Ridgeline Drill', 'stock_quantity' => 10]);

    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'total_cents' => 530_000,
    ]);

    OrderItem::factory()->forProduct($product, 2)->create(['order_id' => $order->id]);

    $this->actingAs($this->customer)
        ->get(route('payment.show', $order))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Payment')
            ->where('order.orderNumber', $order->order_number)
            ->where('order.paymentStatus', PaymentStatus::Pending->value)
            ->where('order.awaitsPayment', true)
            ->where('order.totals.totalCents', 530_000)
            ->has('order.lines', 1)
            ->where('order.lines.0.name', 'Ridgeline Drill')
            ->where('paystackEnabled', true)
            ->where('bankTransferEnabled', true)
            ->where('bankDetails', 'Equity Bank, account 0123456789')
            ->has('breadcrumbs', 2)
        );
});

test('another shoppers order returns 404', function () {
    $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

    // 404 rather than 403: a refusal that confirms the order exists is itself a
    // disclosure.
    $this->actingAs($this->customer)
        ->get(route('payment.show', $order))
        ->assertNotFound();
});

test('a guest is sent to the login page', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    $this->get(route('payment.show', $order))->assertRedirect(route('login'));
});

test('an order that has already been paid redirects to the order', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->get(route('payment.show', $order))
        ->assertRedirect(route('orders.show', $order));
});

test('start returns the access code for the transaction it opened', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['access_code' => '0peioxfhpn', 'authorization_url' => 'https://checkout.paystack.com/0peioxfhpn'],
    ])]);

    $this->actingAs($this->customer)
        ->post(route('payment.start', $order))
        ->assertOk()
        ->assertExactJson(['accessCode' => '0peioxfhpn']);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'gateway' => 'paystack',
        'status' => PaymentStatus::Pending->value,
        'amount_cents' => 530_000,
    ]);
});

test('start returns 404 for another shoppers order', function () {
    $order = Order::factory()->create(['user_id' => User::factory()->create()->id, 'total_cents' => 530_000]);

    $this->actingAs($this->customer)
        ->post(route('payment.start', $order))
        ->assertNotFound();

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('start returns 409 for an order that has already been paid', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->post(route('payment.start', $order))
        ->assertStatus(409)
        ->assertJson(['message' => 'This order has already been paid.']);

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('start returns 502 when paystack cannot be reached', function () {
    Sleep::fake();

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/*' => Http::failedConnection()]);

    $this->actingAs($this->customer)
        ->post(route('payment.start', $order))
        ->assertStatus(502)
        ->assertJson(['message' => 'We could not reach the payment provider. Please try again in a moment.']);

    // A dropped connection is transient, so it is retried before giving up.
    Http::assertSentCount(3);

    // Nothing was opened, so nothing is recorded as an attempt to collect.
    $this->assertDatabaseCount('payments', 0);
});

test('verify sends the shopper to the order when the reference settled', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 10]);

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);
    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'reference' => $order->order_number.'-p1q2r3s4',
        'amount_cents' => 530_000,
        'currency' => 'KES',
    ]);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['id' => 3_004_567, 'status' => 'success', 'amount' => 530_000, 'currency' => 'KES', 'channel' => 'card'],
    ])]);

    $this->actingAs($this->customer)
        ->post(route('payment.verify', $order), ['reference' => $payment->reference])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Success)
        ->and($product->fresh()->stock_quantity)->toBe(6);
});

test('verify refuses a reference that belongs to a different order', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 10]);

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);
    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $other = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 120_000]);

    $otherPayment = Payment::factory()->create([
        'order_id' => $other->id,
        'reference' => $other->order_number.'-t1u2v3w4',
        'amount_cents' => 120_000,
        'currency' => 'KES',
    ]);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['id' => 3_004_568, 'status' => 'success', 'amount' => 120_000, 'currency' => 'KES', 'channel' => 'card'],
    ])]);

    // A reference that really did settle — for somebody else's order.
    $this->actingAs($this->customer)
        ->post(route('payment.verify', $order), ['reference' => $otherPayment->reference])
        ->assertRedirect(route('payment.show', $order))
        ->assertSessionHas('inertia.flash_data.toast.type', 'warning');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('verify leaves the order unpaid when the reference did not settle', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 10]);

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);
    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'reference' => $order->order_number.'-x1y2z3a4',
        'amount_cents' => 530_000,
        'currency' => 'KES',
    ]);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['id' => 3_004_569, 'status' => 'failed', 'amount' => 530_000, 'currency' => 'KES', 'gateway_response' => 'Declined by issuer.'],
    ])]);

    $this->actingAs($this->customer)
        ->post(route('payment.verify', $order), ['reference' => $payment->reference])
        ->assertRedirect(route('payment.show', $order))
        ->assertSessionHas('inertia.flash_data.toast.type', 'warning');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('verify rejects a request with no reference', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    $this->actingAs($this->customer)
        ->post(route('payment.verify', $order), [])
        ->assertSessionHasErrors(['reference' => 'The reference field is required.']);

    Http::assertNothingSent();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

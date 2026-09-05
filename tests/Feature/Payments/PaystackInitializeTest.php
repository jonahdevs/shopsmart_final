<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Paystack\PaystackPaymentService;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Opening a Paystack transaction for an order.
 *
 * Every attempt writes its OWN payment row carrying its OWN reference: the
 * reference is the idempotency key the popup's verify call and the webhook both
 * match on, so rewriting one would leave a settled transaction with no row and
 * lose the money. The amount is frozen onto the row here — that frozen figure,
 * not the live order, is what a later verification is checked against.
 *
 * Nothing reaches the network: every test fakes the gateway and lets a stray
 * request fail the run.
 */
beforeEach(function () {
    Http::preventStrayRequests();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_test_initialize';
    $api->save();

    $this->customer = User::factory()->create();
});

/**
 * A Paystack initialize response body.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paystackInitializeBody(array $overrides = []): array
{
    return array_merge([
        'status' => true,
        'message' => 'Authorization URL created',
        'data' => [
            'authorization_url' => 'https://checkout.paystack.com/0peioxfhpn',
            'access_code' => '0peioxfhpn',
            'reference' => 'ignored-by-us',
        ],
    ], $overrides);
}

test('a successful initialize opens one pending payment carrying the access code in memory', function () {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'customer_email' => 'amina@example.test',
        'total_cents' => 530_000,
        'currency' => 'KES',
    ]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(paystackInitializeBody())]);

    $payment = app(PaystackPaymentService::class)->initialize($order);

    expect(Payment::count())->toBe(1)
        ->and($payment->gateway)->toBe('paystack')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe(530_000)
        ->and($payment->currency)->toBe('KES')
        ->and($payment->order_id)->toBe($order->id)
        ->and($payment->reference)->toStartWith($order->order_number.'-')
        ->and($payment->accessCode())->toBe('0peioxfhpn');

    // A short-lived credential: it travels back in memory and is written to no
    // column, so a row read back from the database carries no trace of it.
    $stored = (array) DB::table('payments')->where('reference', $payment->reference)->sole();

    expect($stored)->not->toContain('0peioxfhpn')
        ->and(Payment::query()->sole()->accessCode())->toBeNull();
});

test('the initialize request carries the email, the amount in cents, the currency, the reference and the order metadata', function () {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'customer_email' => 'amina@example.test',
        'total_cents' => 530_000,
        'currency' => 'KES',
    ]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(paystackInitializeBody())]);

    $payment = app(PaystackPaymentService::class)->initialize($order);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['email'] === 'amina@example.test'
        // KES subunits are cents, so the stored figure goes over the wire as-is.
        && $request['amount'] === 530_000
        && $request['currency'] === 'KES'
        && $request['reference'] === $payment->reference
        && $request['metadata']['order_id'] === $order->id
        && $request['metadata']['order_number'] === $order->order_number);
});

test('a refusal from paystack throws and writes no payment', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => false,
        'message' => 'Invalid amount sent',
    ])]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'Invalid amount sent');

    $this->assertDatabaseCount('payments', 0);
});

test('a 4xx response throws without being retried', function () {
    Sleep::fake();

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => false,
        'message' => 'Invalid key',
    ], 401)]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'Invalid key');

    // A 4xx is Paystack saying the request was wrong; repeating it cannot help.
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();

    $this->assertDatabaseCount('payments', 0);
});

test('a 5xx response is retried twice and then throws', function () {
    Sleep::fake();

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response('', 503)]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'Paystack refused to start the transaction.');

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);

    $this->assertDatabaseCount('payments', 0);
});

test('initialize refuses when the gateway toggle is off', function () {
    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = false;
    $payments->save();

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'Paystack is not configured.');

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('initialize refuses when the toggle is on but no secret key is configured', function () {
    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = null;
    $api->save();

    config()->set('services.paystack.secret_key', '');

    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'Paystack is not configured.');

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('initialize refuses an order that has already been paid', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'This order is not awaiting payment.');

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('initialize refuses a cancelled order', function () {
    $order = Order::factory()->cancelled()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    expect(fn () => app(PaystackPaymentService::class)->initialize($order))
        ->toThrow(RuntimeException::class, 'This order is not awaiting payment.');

    Http::assertNothingSent();
    $this->assertDatabaseCount('payments', 0);
});

test('two attempts open two payments with different references', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(paystackInitializeBody())]);

    $service = app(PaystackPaymentService::class);

    $first = $service->initialize($order);
    $second = $service->initialize($order);

    // Deliberate: reusing a reference would leave a transaction that settled
    // against the first attempt with no row to record it.
    expect(Payment::count())->toBe(2)
        ->and($second->reference)->not->toBe($first->reference)
        ->and($second->id)->not->toBe($first->id);

    Http::assertSentCount(2);
});

<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Paystack\PaystackPaymentService;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Giving money back.
 *
 * Two rules, and both of them exist because the alternative is a real loss. The
 * gateway is called first and the books are written only if it agreed, because
 * a refund recorded but never made is worse than one that was never recorded.
 * And the call is never retried: Paystack accepts no idempotency key on a
 * refund, so repeating a request that actually succeeded but timed out on the
 * way back would pay the customer twice.
 */
beforeEach(function () {
    Http::preventStrayRequests();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_test_refund';
    $api->save();

    $this->customer = User::factory()->create();
});

/** A settled payment of the given size against a paid order. */
function paystackSettledPayment(User $customer, int $amountCents = 530_000): Payment
{
    $order = Order::factory()->paid()->create([
        'user_id' => $customer->id,
        'total_cents' => $amountCents,
        'currency' => 'KES',
    ]);

    return Payment::factory()->successful()->create([
        'order_id' => $order->id,
        'reference' => $order->order_number.'-r1s2t3u4',
        'amount_cents' => $amountCents,
        'currency' => 'KES',
    ]);
}

test('an accepted refund marks the payment refunded', function () {
    $payment = paystackSettledPayment($this->customer, 530_000);

    Http::fake(['api.paystack.co/refund' => Http::response([
        'status' => true,
        'message' => 'Refund has been queued for processing',
        'data' => ['id' => 8_812_004, 'status' => 'pending', 'amount' => 530_000],
    ])]);

    app(PaystackPaymentService::class)->refund($payment, 530_000);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->amount_cents)->toBe(530_000)
        ->and($payment->payload['data']['id'])->toBe(8_812_004);
});

test('the refund request carries the reference, the amount in cents and the currency', function () {
    $payment = paystackSettledPayment($this->customer, 530_000);

    Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 8_812_004]])]);

    // A partial refund: what goes over the wire is what was asked for, not the
    // whole payment.
    app(PaystackPaymentService::class)->refund($payment, 200_000);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paystack.co/refund'
        && $request['transaction'] === $payment->reference
        && $request['amount'] === 200_000
        && $request['currency'] === 'KES');
});

test('a refund paystack rejects throws and leaves the payment settled', function () {
    $payment = paystackSettledPayment($this->customer, 530_000);

    Http::fake(['api.paystack.co/refund' => Http::response([
        'status' => false,
        'message' => 'Transaction has already been fully reversed',
    ])]);

    expect(fn () => app(PaystackPaymentService::class)->refund($payment, 530_000))
        ->toThrow(RuntimeException::class, 'Transaction has already been fully reversed');

    // The books are written only if the gateway agreed.
    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->payload)->toBeNull();
});

test('a refund is not retried when paystack answers with a server error', function () {
    Sleep::fake();

    $payment = paystackSettledPayment($this->customer, 530_000);

    Http::fake(['api.paystack.co/refund' => Http::response('', 503)]);

    expect(fn () => app(PaystackPaymentService::class)->refund($payment, 530_000))
        ->toThrow(RuntimeException::class, 'Paystack rejected the refund.');

    // No idempotency key exists for this call, so a repeat could refund twice.
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);
});

test('a payment that never settled cannot be refunded', function (PaymentStatus $status) {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'total_cents' => 530_000]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => $status,
        'amount_cents' => 530_000,
        'currency' => 'KES',
    ]);

    expect(fn () => app(PaystackPaymentService::class)->refund($payment, 530_000))
        ->toThrow(RuntimeException::class, 'Only a settled payment can be refunded.');

    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe($status);
})->with([
    'still pending' => PaymentStatus::Pending,
    'declined' => PaymentStatus::Failed,
    'abandoned' => PaymentStatus::Cancelled,
    'already refunded' => PaymentStatus::Refunded,
]);

test('a refund outside what the payment took is refused', function (int $amountCents) {
    $payment = paystackSettledPayment($this->customer, 530_000);

    expect(fn () => app(PaystackPaymentService::class)->refund($payment, $amountCents))
        ->toThrow(RuntimeException::class, 'Refund amount is outside what this payment took.');

    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);
})->with([
    'a cent more than was taken' => 530_001,
    'nothing at all' => 0,
    'a negative amount' => -100,
]);

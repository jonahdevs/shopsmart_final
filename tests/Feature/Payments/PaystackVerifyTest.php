<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Paystack\PaystackPaymentService;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use Illuminate\Support\Facades\Http;

/**
 * Asking Paystack what actually happened, and settling on the answer.
 *
 * The browser reports a reference and nothing else. What that reference settled
 * for is decided here, against the amount and currency FROZEN on the payment row
 * at initialisation — never against the live order, which an edit could have
 * moved. An amount or currency that does not match is a rejected payment, not a
 * cheaper sale.
 *
 * Settling is exactly-once: the popup's verify call and the gateway's webhook
 * routinely arrive for the same reference, and taking the stock or redeeming the
 * coupon twice is money.
 */
beforeEach(function () {
    Http::preventStrayRequests();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_test_verify';
    $api->save();

    $this->customer = User::factory()->create();
});

/**
 * An order awaiting payment with one stocked line and a coupon, plus the pending
 * payment row that initialising a transaction would have written for it.
 *
 * @return array{Order, Payment, Product, Coupon}
 */
function paystackAwaitingOrder(User $customer, int $totalCents = 530_000, string $currency = 'KES'): array
{
    $coupon = Coupon::factory()->fixed(50_000)->create();

    $product = Product::factory()->published()->create([
        'stock_quantity' => 10,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'total_cents' => $totalCents,
        'currency' => $currency,
        'coupon_id' => $coupon->id,
        'coupon_code' => $coupon->code,
        'discount_cents' => 50_000,
    ]);

    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'reference' => $order->order_number.'-a1b2c3d4',
        'amount_cents' => $totalCents,
        'currency' => $currency,
    ]);

    return [$order, $payment, $product, $coupon];
}

/**
 * A Paystack verify response body.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function paystackVerifyBody(array $data = []): array
{
    return [
        'status' => true,
        'message' => 'Verification successful',
        'data' => array_merge([
            'id' => 3_004_567,
            'status' => 'success',
            'amount' => 530_000,
            'currency' => 'KES',
            'channel' => 'mobile_money',
            'gateway_response' => 'Successful',
            'authorization' => ['authorization_code' => 'AUTH_9tl6q2b'],
        ], $data),
    ];
}

test('a settled verification marks the payment successful and settles the order', function () {
    [$order, $payment, $product, $coupon] = paystackAwaitingOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackVerifyBody())]);

    $verified = app(PaystackPaymentService::class)->verify($payment->reference);

    expect($verified)->not->toBeNull()
        ->and($verified->id)->toBe($payment->id);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->channel)->toBe('mobile_money')
        ->and($payment->gateway_reference)->toBe('3004567')
        ->and($payment->authorization_code)->toBe('AUTH_9tl6q2b')
        ->and($payment->payload['gateway_response'])->toBe('Successful');

    expect($order->payment_status)->toBe(PaymentStatus::Success)
        ->and($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment_method)->toBe('paystack')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->stock_deducted_at)->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(6)
        ->and($coupon->fresh()->used_count)->toBe(1);

    $this->assertDatabaseCount('coupon_uses', 1);
});

test('an amount smaller than the payment froze is rejected and leaves the order unpaid', function () {
    [$order, $payment, $product, $coupon] = paystackAwaitingOrder($this->customer, 530_000);

    // The exploit this guards: settle a 530,000 order by paying 1,000.
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(
        paystackVerifyBody(['amount' => 1_000])
    )]);

    $verified = app(PaystackPaymentService::class)->verify($payment->reference);

    expect($verified)->toBeNull();

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->amount_cents)->toBe(530_000)
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->failure_reason)->toBe('Amount or currency did not match the transaction we started.');

    expect($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->paid_at)->toBeNull()
        ->and($order->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and($coupon->fresh()->used_count)->toBe(0);

    $this->assertDatabaseCount('coupon_uses', 0);
});

test('a currency other than the one the payment froze is rejected and leaves the order unpaid', function () {
    [$order, $payment, $product, $coupon] = paystackAwaitingOrder($this->customer, 530_000, 'KES');

    // 530,000 of a much stronger unit is not 530,000 shillings.
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(
        paystackVerifyBody(['currency' => 'USD'])
    )]);

    $verified = app(PaystackPaymentService::class)->verify($payment->reference);

    expect($verified)->toBeNull();

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->currency)->toBe('KES')
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->failure_reason)->toBe('Amount or currency did not match the transaction we started.');

    expect($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('a transaction the shopper abandoned cancels the payment and leaves the order unpaid', function () {
    [$order, $payment, $product] = paystackAwaitingOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackVerifyBody([
        'status' => 'abandoned',
        'gateway_response' => 'The customer left the payment page.',
    ]))]);

    expect(app(PaystackPaymentService::class)->verify($payment->reference))->toBeNull();

    $payment->refresh();
    $order->refresh();

    // A closed popup reads very differently to whoever reviews the order later
    // than a card that was declined.
    expect($payment->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->failure_reason)->toBe('The customer left the payment page.')
        ->and($payment->gateway_reference)->toBe('3004567')
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('a declined transaction fails the payment and leaves the order unpaid', function () {
    [$order, $payment, $product] = paystackAwaitingOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackVerifyBody([
        'status' => 'failed',
        'gateway_response' => 'Declined by issuer.',
    ]))]);

    expect(app(PaystackPaymentService::class)->verify($payment->reference))->toBeNull();

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('Declined by issuer.')
        ->and($payment->paid_at)->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('an unknown reference returns null and touches nothing', function () {
    [$order, $payment, $product] = paystackAwaitingOrder($this->customer);

    expect(app(PaystackPaymentService::class)->verify('SS-999999-deadbeef'))->toBeNull();

    // Not even a lookup goes out for a reference we never issued.
    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);

    $this->assertDatabaseCount('payments', 1);
});

test('verifying twice settles once and asks paystack once', function () {
    [$order, $payment, $product, $coupon] = paystackAwaitingOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackVerifyBody())]);

    $service = app(PaystackPaymentService::class);

    $first = $service->verify($payment->reference);
    $second = $service->verify($payment->reference);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second->id)->toBe($first->id);

    // The second call finds the payment already final and never leaves the box.
    Http::assertSentCount(1);

    expect($product->fresh()->stock_quantity)->toBe(6)
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Success);

    $this->assertDatabaseCount('coupon_uses', 1);
});

test('a refused verification leaves the payment pending', function () {
    [$order, $payment, $product] = paystackAwaitingOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => false,
        'message' => 'Transaction reference not found',
    ])]);

    expect(app(PaystackPaymentService::class)->verify($payment->reference))->toBeNull();

    $payment->refresh();

    // Pending, not failed: Paystack did not answer the question, so the shopper
    // must still be able to try again on this order.
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->failure_reason)->toBeNull()
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

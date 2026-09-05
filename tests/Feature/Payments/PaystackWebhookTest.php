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
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paystack's server-to-server notification, and what it is and is not trusted for.
 *
 * The endpoint carries no session and no CSRF token: an HMAC-SHA512 of the RAW
 * body keyed with the secret key is the only thing authenticating it. A body
 * that does not match is answered 400 and nothing happens.
 *
 * A body that DOES match is still only a nudge — the reference is read out of it
 * and re-verified through the API, so a forged notification can at worst ask us
 * to look something up, and Paystack decides the answer. The amount claimed in
 * the body is never money.
 */
const PAYSTACK_WEBHOOK_SECRET = 'sk_test_webhook';

beforeEach(function () {
    Http::preventStrayRequests();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = PAYSTACK_WEBHOOK_SECRET;
    $api->save();

    $this->customer = User::factory()->create();
});

/**
 * An order awaiting payment with one stocked line and a coupon, plus its pending
 * payment row.
 *
 * @return array{Order, Payment, Product, Coupon}
 */
function paystackWebhookOrder(User $customer, int $totalCents = 530_000): array
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
        'currency' => 'KES',
        'coupon_id' => $coupon->id,
        'coupon_code' => $coupon->code,
        'discount_cents' => 50_000,
    ]);

    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'reference' => $order->order_number.'-w1x2y3z4',
        'amount_cents' => $totalCents,
        'currency' => 'KES',
    ]);

    return [$order, $payment, $product, $coupon];
}

/**
 * The raw JSON body of a `charge.success` notification.
 *
 * @param  array<string, mixed>  $data
 */
function paystackWebhookBody(string $reference, array $data = [], string $event = 'charge.success'): string
{
    return (string) json_encode([
        'event' => $event,
        'data' => array_merge([
            'id' => 3_004_567,
            'reference' => $reference,
            'status' => 'success',
            'amount' => 530_000,
            'currency' => 'KES',
        ], $data),
    ]);
}

/**
 * What Paystack answers when we go and ask about the reference.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function paystackWebhookVerifyBody(array $data = []): array
{
    return [
        'status' => true,
        'message' => 'Verification successful',
        'data' => array_merge([
            'id' => 3_004_567,
            'status' => 'success',
            'amount' => 530_000,
            'currency' => 'KES',
            'channel' => 'card',
            'gateway_response' => 'Successful',
        ], $data),
    ];
}

/** POST the exact bytes, with whatever signature was supplied. */
function paystackWebhookPost(TestCase $test, string $body, ?string $signature): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($signature !== null) {
        $server['HTTP_X_PAYSTACK_SIGNATURE'] = $signature;
    }

    return $test->call('POST', route('webhooks.paystack'), [], [], [], $server, $body);
}

test('a correctly signed charge success settles the order', function () {
    [$order, $payment, $product, $coupon] = paystackWebhookOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackWebhookVerifyBody())]);

    $body = paystackWebhookBody($payment->reference);

    paystackWebhookPost($this, $body, hash_hmac('sha512', $body, PAYSTACK_WEBHOOK_SECRET))
        ->assertOk()
        ->assertExactJson(['received' => true]);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Success)
        ->and($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment_method)->toBe('paystack')
        ->and($product->fresh()->stock_quantity)->toBe(6)
        ->and($coupon->fresh()->used_count)->toBe(1);

    $this->assertDatabaseCount('coupon_uses', 1);
});

test('a wrong signature returns 400 and settles nothing', function () {
    [$order, $payment, $product, $coupon] = paystackWebhookOrder($this->customer);

    $body = paystackWebhookBody($payment->reference);

    paystackWebhookPost($this, $body, hash_hmac('sha512', $body, 'sk_test_someone_elses_key'))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid signature.']);

    // A forged body does not even get us to ask Paystack about the reference.
    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('a missing signature header returns 400 and settles nothing', function () {
    [$order, $payment, $product] = paystackWebhookOrder($this->customer);

    paystackWebhookPost($this, paystackWebhookBody($payment->reference), null)
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid signature.']);

    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('an event other than charge success returns 200 and settles nothing', function () {
    [$order, $payment, $product] = paystackWebhookOrder($this->customer);

    $body = paystackWebhookBody($payment->reference, event: 'charge.dispute.create');

    paystackWebhookPost($this, $body, hash_hmac('sha512', $body, PAYSTACK_WEBHOOK_SECRET))
        ->assertOk()
        ->assertExactJson(['received' => true]);

    Http::assertNothingSent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('the amount claimed in the webhook body is ignored in favour of what paystack reports', function () {
    [$order, $payment, $product] = paystackWebhookOrder($this->customer, 530_000);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackWebhookVerifyBody())]);

    // Correctly signed, so the body is authentic — and still not believed about
    // money. Only the reference is taken from it.
    $body = paystackWebhookBody($payment->reference, ['amount' => 99_999_900, 'currency' => 'USD']);

    paystackWebhookPost($this, $body, hash_hmac('sha512', $body, PAYSTACK_WEBHOOK_SECRET))->assertOk();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.paystack.co/transaction/verify/'.urlencode($payment->reference));

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->amount_cents)->toBe(530_000)
        ->and($payment->currency)->toBe('KES')
        ->and($payment->payload['amount'])->toBe(530_000)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Success)
        ->and($product->fresh()->stock_quantity)->toBe(6);
});

test('the webhook settles an order for a shopper who is not signed in', function () {
    [$order, $payment] = paystackWebhookOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackWebhookVerifyBody())]);

    $body = paystackWebhookBody($payment->reference);

    // Paystack is not a user: no session, no CSRF token, no auth redirect.
    paystackWebhookPost($this, $body, hash_hmac('sha512', $body, PAYSTACK_WEBHOOK_SECRET))->assertOk();

    $this->assertGuest();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Success);
});

test('a replayed webhook settles the order once', function () {
    [$order, $payment, $product, $coupon] = paystackWebhookOrder($this->customer);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(paystackWebhookVerifyBody())]);

    $body = paystackWebhookBody($payment->reference);
    $signature = hash_hmac('sha512', $body, PAYSTACK_WEBHOOK_SECRET);

    paystackWebhookPost($this, $body, $signature)->assertOk();
    paystackWebhookPost($this, $body, $signature)->assertOk();

    // The replay finds the payment already final and never asks again.
    Http::assertSentCount(1);

    expect($product->fresh()->stock_quantity)->toBe(6)
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Success);

    $this->assertDatabaseCount('coupon_uses', 1);
    $this->assertDatabaseCount('payments', 1);
});

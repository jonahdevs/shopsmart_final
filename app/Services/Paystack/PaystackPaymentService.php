<?php

namespace App\Services\Paystack;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentCredentials;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Takes money for an order through Paystack.
 *
 * Three rules hold this together, and every one of them exists because the
 * alternative loses or double-takes real money:
 *
 * 1. **The browser is never believed.** The popup reports a reference and
 *    nothing else; whether that reference settled, for how much, in what
 *    currency, is answered by asking Paystack directly.
 * 2. **The amount is checked against the Payment row, not the live order.** The
 *    row froze the figure when the transaction was initialised, so an order
 *    edited in between cannot be used to authorise a smaller payment.
 * 3. **A reference is written once and never reused.** It is the idempotency
 *    key shared by the popup's verify call and the asynchronous webhook —
 *    whichever arrives first settles the payment, and the other finds it final.
 *
 * Paystack fronts cards, M-Pesa, Airtel Money and bank transfer through one
 * integration, so no channel is requested: the popup offers whatever the
 * merchant account has enabled.
 */
class PaystackPaymentService
{
    public function __construct(
        private PaystackClient $client,
        private PaymentCredentials $credentials,
    ) {}

    public function enabled(): bool
    {
        return $this->credentials->paystackEnabled();
    }

    /**
     * Open a transaction for this order and return the Payment holding its
     * access code.
     *
     * A NEW payment row is written on every attempt. Rewriting an existing
     * row's reference would be cheaper, but it opens a hole: a shopper who pays
     * and then re-opens the popup before the webhook lands would leave the
     * settled transaction with no row to match, and the money would go
     * unrecorded. Abandoned attempts are cheap; lost payments are not.
     *
     * @throws RuntimeException when the gateway is unavailable or rejects the request
     */
    public function initialize(Order $order): Payment
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        if (! $order->awaitsPayment()) {
            throw new RuntimeException('This order is not awaiting payment.');
        }

        $reference = $order->order_number.'-'.Str::lower(Str::random(8));

        $response = $this->client->initializeTransaction([
            'email' => $order->customer_email,
            // KES subunits are cents, so the stored figure goes as-is.
            'amount' => $order->total_cents,
            'currency' => $order->currency,
            'reference' => $reference,
            'metadata' => [
                'order_id' => $order->getKey(),
                'order_number' => $order->order_number,
            ],
        ]);

        $accessCode = Arr::get($response, 'data.access_code');

        if (Arr::get($response, 'status') !== true || ! is_string($accessCode) || $accessCode === '') {
            throw new RuntimeException(
                (string) (Arr::get($response, 'message') ?? 'Paystack refused to start the transaction.')
            );
        }

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'reference' => $reference,
            'gateway' => 'paystack',
            'status' => PaymentStatus::Pending,
            // Frozen here. This is what a verification is checked against.
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        // Carried in memory only: an access code is a short-lived credential
        // and has no business being persisted.
        return $payment->withAccessCode($accessCode);
    }

    /**
     * Confirm a transaction with Paystack and settle it if it really paid.
     *
     * Returns the payment only when it actually succeeded, so a caller can
     * never advance an order on a failed or mismatched verification. Safe to
     * call repeatedly: a payment that has already reached a final state is
     * returned as-is rather than re-processed.
     */
    public function verify(string $reference): ?Payment
    {
        $payment = Payment::query()->where('reference', $reference)->first();

        if ($payment === null) {
            return null;
        }

        if ($payment->isFinal()) {
            return $payment->status === PaymentStatus::Success ? $payment : null;
        }

        $response = $this->client->verifyTransaction($reference);

        if (Arr::get($response, 'status') !== true) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = Arr::get($response, 'data', []);

        $this->settle($payment, $data);

        $payment->refresh();

        return $payment->status === PaymentStatus::Success ? $payment : null;
    }

    /**
     * Whether this body really came from Paystack.
     *
     * The signature is an HMAC-SHA512 of the RAW request body keyed with the
     * secret key, compared with `hash_equals` so a wrong one cannot be
     * discovered a byte at a time by timing the response.
     *
     * Deliberately a predicate rather than a thrown exception: the caller
     * answers 400 for a bad signature, and routing that through an exception
     * would mean catching a type that anything downstream might also throw,
     * turning a genuine processing failure into a misleading "invalid
     * signature" reply.
     */
    public function signatureMatches(string $rawPayload, string $signature): bool
    {
        $secret = $this->credentials->paystackSecretKey();

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawPayload, $secret), $signature);
    }

    /**
     * Act on a webhook body whose signature has already been checked.
     *
     * The body is used for one thing only — the reference — which is then
     * re-verified through the API. A forged body can therefore at worst ask us
     * to look up a transaction, and Paystack still decides the answer.
     */
    public function handleWebhook(string $rawPayload): void
    {
        /** @var array<string, mixed> $event */
        $event = json_decode($rawPayload, true) ?: [];

        if (Arr::get($event, 'event') !== 'charge.success') {
            return;
        }

        $reference = Arr::get($event, 'data.reference');

        if (is_string($reference) && $reference !== '') {
            $this->verify($reference);
        }
    }

    /**
     * Refund a settled payment, wholly or in part.
     *
     * The gateway is called first and the books are written only if it agreed —
     * recording a refund that was rejected would be worse than not recording
     * one at all.
     *
     * @throws RuntimeException when the amount is not refundable or Paystack refuses
     */
    public function refund(Payment $payment, int $amountCents): void
    {
        if ($payment->status !== PaymentStatus::Success) {
            throw new RuntimeException('Only a settled payment can be refunded.');
        }

        if ($amountCents < 1 || $amountCents > $payment->amount_cents) {
            throw new RuntimeException('Refund amount is outside what this payment took.');
        }

        $response = $this->client->createRefund([
            'transaction' => $payment->reference,
            'amount' => $amountCents,
            'currency' => $payment->currency,
        ]);

        if (! $response->successful() || $response->json('status') !== true) {
            throw new RuntimeException(
                (string) ($response->json('message') ?? 'Paystack rejected the refund.')
            );
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'payload' => $response->json(),
        ])->save();
    }

    /**
     * Write the outcome of a verification onto the payment, and advance the
     * order when it really paid.
     *
     * @param  array<string, mixed>  $data
     */
    private function settle(Payment $payment, array $data): void
    {
        if (Arr::get($data, 'status') !== 'success') {
            $payment->forceFill([
                // Paystack calls a popup the customer closed "abandoned"; that
                // is a cancellation, not a failure, and the two read very
                // differently to whoever reviews the order later.
                'status' => Arr::get($data, 'status') === 'abandoned'
                    ? PaymentStatus::Cancelled
                    : PaymentStatus::Failed,
                'failure_reason' => (string) (Arr::get($data, 'gateway_response') ?? 'Payment did not complete.'),
                'gateway_reference' => $this->gatewayReference($data),
                'payload' => $data,
            ])->save();

            return;
        }

        $amount = (int) Arr::get($data, 'amount');
        $currency = mb_strtolower((string) Arr::get($data, 'currency'));

        // The security gate. Compared against the frozen row, never the live
        // order, so an order edited between initialisation and settlement
        // cannot be used to authorise a payment for less than it cost.
        if ($amount !== $payment->amount_cents || $currency !== mb_strtolower($payment->currency)) {
            Log::critical('Paystack settled an amount that does not match the payment. Rejected.', [
                'reference' => $payment->reference,
                'expected_amount_cents' => $payment->amount_cents,
                'reported_amount_cents' => $amount,
                'expected_currency' => $payment->currency,
                'reported_currency' => $currency,
            ]);

            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => 'Amount or currency did not match the transaction we started.',
                // Kept queryable rather than left buried in the encrypted
                // payload: this is precisely the case where someone will need
                // to look the transaction up by id at the gateway.
                'gateway_reference' => $this->gatewayReference($data),
                'payload' => $data,
            ])->save();

            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Success,
            'channel' => Arr::get($data, 'channel'),
            'gateway_reference' => $this->gatewayReference($data),
            'authorization_code' => Arr::get($data, 'authorization.authorization_code'),
            'payload' => $data,
            'paid_at' => now(),
        ])->save();

        // The order's own guard decides whether this is the confirmation that
        // counts; a second one returns false and changes nothing.
        $payment->order?->markPaid('paystack');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function gatewayReference(array $data): ?string
    {
        $id = Arr::get($data, 'id');

        return $id === null ? null : (string) $id;
    }
}

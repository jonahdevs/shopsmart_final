<?php

namespace App\Services\Paystack;

use App\Services\PaymentCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin wrapper over Paystack's HTTP API.
 *
 * Nothing here interprets a response — that is the service's job. This exists
 * so there is one place that knows the base URL, the bearer token, the timeouts
 * and which calls may safely be retried, and so tests have a single seam to
 * fake.
 *
 * The timeouts are not optional. A gateway that stops answering must fail the
 * request rather than hold a web worker (or a webhook worker) open until PHP's
 * own limit kills it.
 */
class PaystackClient
{
    /** Seconds to wait for the connection, then for the response. */
    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 15;

    /** Backoff in milliseconds between retries of a safely repeatable call. */
    private const BACKOFF = [200, 1000];

    public function __construct(private PaymentCredentials $credentials) {}

    /**
     * Start a transaction.
     *
     * Retried on transient failures because the `reference` in the payload is
     * an idempotency key: Paystack returns the existing transaction for a
     * reference it has already seen rather than opening a second one, so a
     * repeat cannot double-charge.
     *
     * The amount is in the currency's subunit, which for KES is cents and
     * therefore matches our stored figures with no conversion.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initializeTransaction(array $payload): array
    {
        return $this->request()
            ->retry(self::BACKOFF, 0, $this->whenTransient(...), throw: false)
            ->post('/transaction/initialize', $payload)
            ->json() ?? [];
    }

    /**
     * Look up a transaction by our reference.
     *
     * This is the authority on whether money moved — never a webhook body,
     * which is only a nudge to come and ask. A plain read, so it is retried
     * freely.
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $reference): array
    {
        return $this->request()
            ->retry(self::BACKOFF, 0, $this->whenTransient(...), throw: false)
            ->get('/transaction/verify/'.urlencode($reference))
            ->json() ?? [];
    }

    /**
     * Refund a transaction.
     *
     * Deliberately NOT retried: Paystack accepts no idempotency key here, so a
     * repeat of a request that actually succeeded but timed out on the way back
     * would refund the customer twice.
     *
     * Returns the raw response rather than decoded JSON so the caller can tell
     * a queued refund from a rejected one before trusting the body.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createRefund(array $payload): Response
    {
        return $this->request()->post('/refund', $payload);
    }

    /**
     * Retry only what will plausibly succeed on a second attempt: a refused or
     * dropped connection, a rate limit, or the gateway's own 5xx. A 4xx is
     * Paystack telling us the request was wrong, and repeating it will not make
     * it right.
     */
    private function whenTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->serverError() || $exception->response->status() === 429);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url', 'https://api.paystack.co'))
            ->withToken($this->credentials->paystackSecretKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }
}

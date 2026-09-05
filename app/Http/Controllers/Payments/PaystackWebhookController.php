<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Paystack\PaystackPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paystack's server-to-server notification that a charge went through.
 *
 * This is the backstop for the inline popup: if the customer closes the tab
 * before the browser can confirm, this is what still settles the order.
 *
 * `getContent()` gives the RAW body, which is essential — the signature is an
 * HMAC over the exact bytes Paystack sent, and re-encoding a decoded array
 * would change them.
 *
 * A bad signature is answered 400 and nothing else happens. Any other failure is
 * allowed to escape as a 500 on purpose: Paystack retries a non-2xx, and a
 * transient error here should be retried rather than silently swallowed with an
 * "OK" that loses the payment.
 *
 * The signature check is a predicate rather than a caught exception, so a
 * genuine processing failure downstream can never be reported back as
 * "invalid signature".
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackPaymentService $paystack): JsonResponse
    {
        $body = $request->getContent();

        if (! $paystack->signatureMatches($body, (string) $request->header('x-paystack-signature', ''))) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $paystack->handleWebhook($body);

        return response()->json(['received' => true]);
    }
}

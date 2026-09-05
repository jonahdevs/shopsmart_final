<?php

namespace App\Http\Controllers\Shop;

use App\Data\BreadcrumbData;
use App\Data\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentCredentials;
use App\Services\Paystack\PaystackPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Paying for an order that has already been placed.
 *
 * Kept off the checkout page deliberately: the order exists and is owed money
 * from the moment it is placed, so paying is a separate, repeatable step. A
 * shopper who closes the popup, loses their connection or comes back tomorrow
 * lands here and tries again, rather than rebuilding a cart they already
 * committed.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Order $order, PaymentCredentials $credentials): Response|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()?->getKey(), 404);

        if (! $order->awaitsPayment()) {
            return to_route('orders.show', $order);
        }

        $order->load('items');

        return Inertia::render('shop/Payment', [
            'order' => OrderData::fromModel($order),
            'paystackEnabled' => $credentials->paystackEnabled(),
            'bankTransferEnabled' => $credentials->bankTransferEnabled(),
            'bankDetails' => $credentials->bankDetails(),
            'breadcrumbs' => [
                new BreadcrumbData(name: __('Home'), slug: null),
                new BreadcrumbData(name: __('Pay for :number', ['number' => $order->order_number]), slug: null),
            ],
        ]);
    }

    /**
     * Open a Paystack transaction and hand the browser its access code.
     *
     * Returns JSON rather than an Inertia redirect because the popup is opened
     * from JavaScript: the page must stay exactly as it is while the gateway
     * takes over, and a redirect would tear it down.
     */
    public function start(Request $request, Order $order, PaystackPaymentService $paystack): JsonResponse
    {
        abort_unless($order->user_id === $request->user()?->getKey(), 404);

        if (! $order->awaitsPayment()) {
            return response()->json(['message' => __('This order has already been paid.')], 409);
        }

        try {
            $payment = $paystack->initialize($order);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('We could not reach the payment provider. Please try again in a moment.'),
            ], 502);
        }

        return response()->json(['accessCode' => $payment->accessCode()]);
    }

    /**
     * Confirm what the popup reported.
     *
     * The browser sends a reference and nothing else; whether it settled, for
     * how much, is decided by asking Paystack. A reference that did not pay
     * leaves the order exactly where it was.
     */
    public function verify(Request $request, Order $order, PaystackPaymentService $paystack): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()?->getKey(), 404);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:191'],
        ]);

        // The reference is matched to THIS order before the gateway is called,
        // not after. Verifying first would let one shopper's request settle a
        // different order as a side effect — harmless in itself, since that
        // payment really did succeed, but it is not this request's business to
        // touch another order's row.
        $attempt = $order->payments()->where('reference', $validated['reference'])->first();

        $payment = null;

        if ($attempt !== null) {
            try {
                $payment = $paystack->verify($attempt->reference);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($payment === null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('We could not confirm that payment. If you were charged, the confirmation will arrive shortly — please do not pay again.'),
            ]);

            return to_route('payment.show', $order);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment received. Order :number is now being processed.', ['number' => $order->order_number]),
        ]);

        return to_route('orders.show', $order);
    }
}

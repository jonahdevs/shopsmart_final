<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps staff out of the shopper-only side of the storefront.
 *
 * Staff accounts exist to run the store, not to buy from it; an order placed by
 * one would pollute the sales figures it is their job to read. They are sent
 * back to the cart with an explanation rather than shown a 403, because the
 * link that brought them here is one the storefront itself rendered — a hard
 * refusal on your own button reads as a bug, not a rule.
 */
class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isStaff()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Staff accounts cannot place orders. Sign in as a customer to check out.'),
            ]);

            return to_route('cart.index');
        }

        return $next($request);
    }
}

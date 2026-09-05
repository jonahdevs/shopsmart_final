<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ApplyCouponRequest;
use App\Support\StorefrontSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Applying and removing the discount code held against the session.
 *
 * Only the code is stored. What it is worth depends on the cart, so it is
 * re-resolved and re-validated on every render and again at placement — a
 * figure cached here would go stale the moment a line changed.
 */
class CheckoutCouponController extends Controller
{
    public function store(ApplyCouponRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        $storefront->applyCoupon($request->code());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Code :code applied.', ['code' => $request->code()]),
        ]);

        return back();
    }

    public function destroy(StorefrontSession $storefront): RedirectResponse
    {
        $storefront->clearCoupon();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Discount code removed.'),
        ]);

        return back();
    }
}

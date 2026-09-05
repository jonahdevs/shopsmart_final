<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * The customer's address book.
 *
 * Only ever reachable behind the `customer` middleware, and every action scopes
 * to the signed-in user rather than trusting an id from the request — an
 * address is the one piece of a shopper's record that another shopper must
 * never be able to read or move.
 */
class AddressController extends Controller
{
    public function store(StoreAddressRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $address = Address::query()->create($request->addressAttributes());

            if ($address->is_default) {
                $this->demoteOthers($address);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Address saved.'),
        ]);

        // No id is flashed back: the checkout page preselects the new address by
        // spotting the addition in its own `addresses` prop, which works on a
        // plain redirect and needs nothing shared through the session.
        return back(fallback: route('checkout.index'));
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()?->getKey(), 404);

        $address->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Address removed.'),
        ]);

        return back();
    }

    /**
     * Exactly one address per customer carries the default flag.
     *
     * Written as a scoped UPDATE rather than a loop so the invariant holds in
     * one statement, whatever else is happening concurrently.
     */
    private function demoteOthers(Address $address): void
    {
        Address::query()
            ->where('user_id', $address->user_id)
            ->whereKeyNot($address->getKey())
            ->update(['is_default' => false]);
    }
}

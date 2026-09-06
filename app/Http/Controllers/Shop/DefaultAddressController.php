<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Which address the checkout preselects.
 *
 * Its own controller because "the default address" is a single thing the
 * customer owns, not a field on each address: promoting one necessarily demotes
 * the rest, and modelling that as an update to one row invites a second one to
 * be promoted without the first being demoted.
 *
 * Both statements run inside a transaction and are scoped to the signed-in
 * user, so the invariant — exactly one default per customer — holds even if two
 * tabs race each other.
 */
class DefaultAddressController extends Controller
{
    public function update(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()?->getKey(), 404);

        DB::transaction(function () use ($address): void {
            Address::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->getKey())
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Default address updated.'),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The fork in the road after signing in.
 *
 * Fortify sends everyone to `dashboard`, and the route name has to stay so it
 * keeps working, but the two kinds of account want completely different pages:
 * a customer wants their orders, a staff member wants the store's.
 *
 * Both sides are now redirects, so each area has one canonical URL and this
 * route is nothing but the fork. A customer who bookmarked `dashboard` before
 * the account area existed still lands somewhere real, and so does a staff
 * member who bookmarked it before the admin panel did.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->isCustomer()) {
            return to_route('account.dashboard');
        }

        return to_route('admin.dashboard');
    }
}

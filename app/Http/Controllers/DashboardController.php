<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fork in the road after signing in.
 *
 * Fortify sends everyone to `dashboard`, and the route name has to stay so it
 * keeps working, but the two kinds of account want completely different pages:
 * a customer wants their orders, a staff member wants the store's. Customers
 * are forwarded to the account area; staff keep the placeholder until phase 7
 * replaces it with the real admin overview.
 *
 * The branch is a redirect rather than a second component so the account area
 * has one canonical URL — a customer who bookmarks this page lands somewhere
 * that still exists after phase 7 takes the staff dashboard over.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isCustomer()) {
            return to_route('account.dashboard');
        }

        return Inertia::render('Dashboard');
    }
}

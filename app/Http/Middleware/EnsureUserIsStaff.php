<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps shoppers out of the admin panel.
 *
 * The mirror of {@see EnsureUserIsCustomer}, but it refuses rather than
 * redirects. A staff member who reaches a customer-only page followed a link the
 * storefront rendered for them, so they get an explanation; a customer who
 * reaches `/admin` typed it, and the honest answer to that is 403.
 *
 * Staff membership is "holds at least one role" — see {@see User::isStaff()}.
 * What a given staff member may then *do* is decided per route by
 * `can:` middleware against the permissions PermissionSeeder defines; this
 * middleware only guards the door.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->isStaff(), 403);

        return $next($request);
    }
}

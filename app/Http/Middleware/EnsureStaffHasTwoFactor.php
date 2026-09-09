<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds the admin panel shut against a staff member who has not enrolled an
 * authenticator, when the store has said it requires one.
 *
 * A redirect rather than a 403: the person is staff and is allowed here, they
 * are simply one step short. The security screen they are sent to is the one
 * that enrols them, it sits outside the admin routes so there is no loop, and
 * it is the General tab of the same settings area — so they land somewhere that
 * still looks like the panel they were heading for.
 *
 * `two_factor_confirmed_at` is the bar rather than `two_factor_secret`, because
 * Fortify writes the secret the moment enrolment starts. A half-finished
 * enrolment is not a second factor.
 */
class EnsureStaffHasTwoFactor
{
    public function __construct(private SecuritySettings $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->security->require_two_factor) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        return redirect()->route('security.edit')->with('toast', [
            'type' => 'warning',
            'message' => __('Two-factor authentication is required for staff. Set it up to reach the admin panel.'),
        ]);
    }
}

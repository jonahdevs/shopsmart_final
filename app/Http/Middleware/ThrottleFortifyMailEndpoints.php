<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify maps rate limiters for login, two-factor, passkeys and email
 * verification only. That leaves the two endpoints which send mail to an
 * address chosen by the caller — the password reset request and registration —
 * with no ceiling at all, so a script can both enumerate accounts and use the
 * store as a mail relay.
 *
 * The limiters are attached here rather than onto the route objects because
 * Fortify registers those routes from its own service provider, and the
 * ordering between that and any `booted` hook is not guaranteed.
 */
class ThrottleFortifyMailEndpoints
{
    /**
     * Rate limiter name, keyed by the Fortify route it guards.
     *
     * @var array<string, string>
     */
    private const LIMITERS = [
        'password.email' => 'password-reset-link',
        'register.store' => 'registration',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, $limiter);
    }
}

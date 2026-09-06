<?php

use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleFortifyMailEndpoints;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'customer' => EnsureUserIsCustomer::class,
            'staff' => EnsureUserIsStaff::class,
        ]);

        // The gateway webhook is server-to-server and carries no session, so it
        // has no CSRF token to send. Its HMAC signature over the raw body is
        // what authenticates it — see PaystackPaymentService::handleWebhook().
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/paystack',
        ]);

        $middleware->web(append: [
            AuthenticateSession::class,
            ThrottleFortifyMailEndpoints::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Branded error pages, rendered through Inertia so a shopper who hits a
         * dead link keeps the storefront's chrome and a way back to the shop,
         * rather than being dropped onto Symfony's bare page.
         *
         * Only the statuses a shopper can actually provoke are handled. Debug
         * mode is left alone entirely — a developer needs the stack trace, and
         * a friendly page that hides it would be a step backwards locally. The
         * `api/*` and JSON cases are already excluded by the rule above.
         */
        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request): SymfonyResponse {
            if (app()->hasDebugModeEnabled() || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if (! in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // 419 is a stale CSRF token, not a broken link: the session simply
            // expired in a tab left open. Sending them back to the form they
            // were on with a refreshed token is more useful than an error page.
            if ($response->getStatusCode() === 419) {
                return back()->with('toast', [
                    'type' => 'warning',
                    'message' => __('Your session expired. Please try again.'),
                ]);
            }

            return Inertia::render('errors/Error', [
                'status' => $response->getStatusCode(),
            ])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });
    })->create();

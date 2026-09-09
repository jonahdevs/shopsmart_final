<?php

namespace App\Http\Middleware;

use App\Settings\MaintenanceSettings;
use App\Support\StorefrontCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the shop floor without closing the shop.
 *
 * Staff pass straight through, which is the whole point of a settings-driven
 * close over `artisan down`: someone has to be able to fix whatever the shop was
 * closed for, and see the result before reopening. Everyone else gets a 503
 * carrying the message the Maintenance screen holds — the exception handler
 * renders it on the branded error page.
 *
 * Applied to the storefront routes only. The admin panel, the auth screens and
 * the account pages stay reachable so staff can sign in to a closed shop.
 */
class EnsureStoreIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = $this->maintenance();

        if (! $maintenance['mode']) {
            return $next($request);
        }

        if ((bool) $request->user()?->isStaff()) {
            return $next($request);
        }

        /*
          The gateway callback is not a shopper. Refusing it because the shop is
          closed would drop the notification for a payment that has already been
          taken, and Paystack would stop retrying long before anyone reopened.
        */
        if ($request->is('api/*')) {
            return $next($request);
        }

        abort(503, $maintenance['message']);
    }

    /**
     * Read through the cache: this runs on every storefront request, and the
     * settings package's own cache is off by default.
     *
     * @return array{mode: bool, message: string}
     */
    private function maintenance(): array
    {
        return Cache::remember(StorefrontCache::MAINTENANCE, now()->addHour(), function (): array {
            $settings = app(MaintenanceSettings::class);

            return [
                'mode' => $settings->maintenance_mode,
                'message' => $settings->maintenance_message,
            ];
        });
    }
}

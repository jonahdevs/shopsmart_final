<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\StorefrontSession;
use Illuminate\Auth\Events\Login;

/**
 * Reconcile the cart, wishlist and compare tray a shopper arrived with against
 * the ones they left behind last time.
 *
 * Registered by Laravel's event discovery, which scans `app/Listeners` for a
 * `handle()` typed against an event — no manual wiring.
 *
 * Runs synchronously and inline: the merge decides what the very next response
 * renders, and the guest session it reads from is gone by the time a queued job
 * would run.
 *
 * The event carries the user because the session guard fires it before it sets
 * the resolved user on itself.
 */
class SyncCartOnLogin
{
    public function __construct(private StorefrontSession $storefront) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->storefront->mergeIntoUser($event->user);
    }
}

<?php

namespace App\Settings;

use App\Http\Middleware\EnsureStaffHasTwoFactor;
use Spatie\LaravelSettings\Settings;

/**
 * The two security decisions the store gets to make for itself.
 *
 * Both are enforced where they cost nothing to read: the two-factor rule by
 * {@see EnsureStaffHasTwoFactor} on the admin routes, and
 * the throttle inside Fortify's `login` rate limiter, which only runs when
 * somebody is signing in.
 */
class SecuritySettings extends Settings
{
    public bool $require_two_factor;

    public int $login_attempts_per_minute;

    public static function group(): string
    {
        return 'security';
    }
}

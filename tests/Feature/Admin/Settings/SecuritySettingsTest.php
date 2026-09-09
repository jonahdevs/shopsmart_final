<?php

use App\Models\User;
use App\Settings\SecuritySettings;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * The Security screen, and the two rules it actually turns on.
 *
 * The screen is the easy half. What is worth pinning is that the two-factor
 * rule holds the admin door without holding the enrolment page it sends people
 * to — a redirect loop there would lock every staff member out of the whole
 * application — and that the throttle reads the stored number rather than
 * Fortify's hard-coded five.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);
});

function securityManager(bool $withTwoFactor = true): User
{
    $user = User::factory()->create([
        'two_factor_confirmed_at' => $withTwoFactor ? now() : null,
    ]);

    $user->assignRole('Admin');

    return $user;
}

function requireTwoFactor(): void
{
    $settings = app(SecuritySettings::class);
    $settings->require_two_factor = true;
    $settings->save();
}

it('shows the security screen and whether the viewer has an authenticator', function () {
    $this->actingAs(securityManager(withTwoFactor: false))
        ->get(route('admin.settings.security'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/settings/Security')
            ->where('security.require_two_factor', false)
            ->where('security.login_attempts_per_minute', 5)
            ->where('viewerHasTwoFactor', false),
        );
});

it('refuses a staff member without settings.manage', function () {
    $manager = tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));

    $this->actingAs($manager)
        ->get(route('admin.settings.security'))
        ->assertForbidden();
});

it('saves both rules', function () {
    $this->actingAs(securityManager())
        ->put(route('admin.settings.security.update'), [
            'require_two_factor' => '1',
            'login_attempts_per_minute' => 12,
        ])
        ->assertRedirect(route('admin.settings.security'));

    $settings = app(SecuritySettings::class)->refresh();

    expect($settings->require_two_factor)->toBeTrue()
        ->and($settings->login_attempts_per_minute)->toBe(12);
});

it('rejects a throttle outside the bounds a real person needs', function () {
    $this->actingAs(securityManager())
        ->put(route('admin.settings.security.update'), [
            'login_attempts_per_minute' => 1,
        ])
        ->assertSessionHasErrors('login_attempts_per_minute');
});

it('lets staff into the panel while two-factor is not required', function () {
    $this->actingAs(securityManager(withTwoFactor: false))
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('sends a staff member without an authenticator to their own security page', function () {
    requireTwoFactor();

    $this->actingAs(securityManager(withTwoFactor: false))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

it('leaves the enrolment page reachable, so the redirect cannot loop', function () {
    requireTwoFactor();

    $this->actingAs(securityManager(withTwoFactor: false))
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

it('lets a staff member with a confirmed authenticator through', function () {
    requireTwoFactor();

    $this->actingAs(securityManager())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('treats an unfinished enrolment as no second factor', function () {
    requireTwoFactor();

    $halfEnrolled = securityManager(withTwoFactor: false);
    $halfEnrolled->forceFill(['two_factor_secret' => encrypt('anything')])->save();

    $this->actingAs($halfEnrolled)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

it('throttles sign-in at the number the store stored', function () {
    $settings = app(SecuritySettings::class);
    $settings->login_attempts_per_minute = 3;
    $settings->save();

    $credentials = ['email' => 'nobody@example.test', 'password' => 'wrong-password'];

    // Three wrong passwords are refused by the form, which is the limiter still
    // letting them through.
    foreach (range(1, 3) as $attempt) {
        $this->post(route('login'), $credentials)->assertSessionHasErrors('email');
    }

    // The fourth never reaches the form. Fortify's default is five, so a 429
    // here is the stored number being read rather than the hard-coded one.
    $this->post(route('login'), $credentials)->assertStatus(429);
});

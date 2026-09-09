<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;

/**
 * The Cache screen.
 *
 * The screen hands a staff member artisan commands, so the thing worth pinning
 * is the closed list: only the five keys below may run anything, and a request
 * naming something else is a validation failure rather than a command.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);
});

function cacheManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin'));
}

it('shows the cache screen', function () {
    $this->actingAs(cacheManager())
        ->get(route('admin.settings.cache'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/settings/Cache'));
});

it('refuses a staff member without settings.manage', function () {
    $manager = tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));

    $this->actingAs($manager)
        ->get(route('admin.settings.cache'))
        ->assertForbidden();
});

it('clears the cache a staff member asked for', function () {
    Artisan::spy();

    $this->actingAs(cacheManager())
        ->post(route('admin.settings.cache.update'), ['cache' => 'config'])
        ->assertRedirect(route('admin.settings.cache'));

    Artisan::shouldHaveReceived('call')->once()->with('config:clear');
});

it('runs all four for the everything option, and nothing else', function () {
    Artisan::spy();

    $this->actingAs(cacheManager())
        ->post(route('admin.settings.cache.update'), ['cache' => 'all'])
        ->assertRedirect(route('admin.settings.cache'));

    // `once()` here would count every `call`, not the ones matching the
    // argument, so the count is asserted separately from the four names.
    Artisan::shouldHaveReceived('call')->times(4);

    foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
        Artisan::shouldHaveReceived('call')->with($command);
    }
});

it('refuses a command that is not on the list', function () {
    Artisan::spy();

    $this->actingAs(cacheManager())
        ->post(route('admin.settings.cache.update'), ['cache' => 'optimize'])
        ->assertSessionHasErrors('cache');

    Artisan::shouldNotHaveReceived('call');
});

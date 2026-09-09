<?php

use App\Models\User;
use App\Settings\MaintenanceSettings;
use App\Support\StorefrontCache;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * The Maintenance screen, and the close it actually performs.
 *
 * The screen itself is the same shape as the other settings screens, so the
 * interesting part is the middleware: a closed shop has to stay open to the
 * people who closed it, to the gateway reporting on money already taken, and to
 * a customer's own account — and it has to say what staff wrote rather than the
 * generic sentence every other 503 shows.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);
});

function maintenanceManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin'));
}

function closeTheShop(string $message = 'Back at 6pm.'): void
{
    $settings = app(MaintenanceSettings::class);
    $settings->maintenance_mode = true;
    $settings->maintenance_message = $message;
    $settings->save();

    // Written behind the controller, so the cached copy has to go too.
    StorefrontCache::forgetMaintenance();
}

it('shows the maintenance screen to a staff member who may manage settings', function () {
    $this->actingAs(maintenanceManager())
        ->get(route('admin.settings.maintenance'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/settings/Maintenance')
            ->where('maintenance.maintenance_mode', false),
        );
});

it('refuses a staff member without settings.manage', function () {
    $manager = tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));

    $this->actingAs($manager)
        ->get(route('admin.settings.maintenance'))
        ->assertForbidden();
});

it('saves the mode and the message', function () {
    $this->actingAs(maintenanceManager())
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_mode' => '1',
            'maintenance_message' => '  Stocktaking until Monday.  ',
        ])
        ->assertRedirect(route('admin.settings.maintenance'));

    $settings = app(MaintenanceSettings::class)->refresh();

    expect($settings->maintenance_mode)->toBeTrue()
        ->and($settings->maintenance_message)->toBe('Stocktaking until Monday.');
});

it('rejects an empty message, because it is all a closed shop says', function () {
    $this->actingAs(maintenanceManager())
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_mode' => '1',
            'maintenance_message' => '',
        ])
        ->assertSessionHasErrors('maintenance_message');
});

it('treats an absent checkbox as reopening the shop', function () {
    closeTheShop();

    $this->actingAs(maintenanceManager())
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_message' => 'Anything.',
        ]);

    expect(app(MaintenanceSettings::class)->refresh()->maintenance_mode)->toBeFalse();
});

it('leaves the storefront open while the shop is not closed', function () {
    $this->get(route('home'))->assertOk();
});

it('closes the storefront to a guest, carrying the staff message', function () {
    // The handler stands aside in debug mode so a developer keeps the stack
    // trace; this assertion is about what production serves.
    config()->set('app.debug', false);

    closeTheShop('Back at 6pm.');

    $this->get(route('home'))
        ->assertStatus(503)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('errors/Error')
            ->where('status', 503)
            ->where('detail', 'Back at 6pm.'),
        );
});

it('closes the storefront to a signed-in customer', function () {
    closeTheShop();

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertStatus(503);
});

it('keeps the storefront open to staff so they can check the change', function () {
    closeTheShop();

    $this->actingAs(maintenanceManager())
        ->get(route('home'))
        ->assertOk();
});

it('keeps the admin panel open while the shop is closed', function () {
    closeTheShop();

    $this->actingAs(maintenanceManager())
        ->get(route('admin.settings.maintenance'))
        ->assertOk();
});

it('keeps the gateway webhook reachable while the shop is closed', function () {
    closeTheShop();

    // No signature, so the controller refuses it — but with its own status,
    // which is the point: the request reached the controller rather than 503.
    $this->postJson(route('webhooks.paystack'), [])
        ->assertStatus(400);
});

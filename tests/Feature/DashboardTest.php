<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

/**
 * `dashboard` is where Fortify lands everyone, and since phase 6 it forks by
 * role: a customer is forwarded to their account, staff keep the page. The
 * branch itself is covered in tests/Feature/Account/AccountDashboardTest.php.
 */
test('authenticated staff can visit the dashboard', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Support');

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an authenticated customer is forwarded to their account', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('account.dashboard'));
});

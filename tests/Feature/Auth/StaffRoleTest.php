<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('a user without any role is a customer and not staff', function () {
    $user = User::factory()->create();

    expect($user->isCustomer())->toBeTrue()
        ->and($user->isStaff())->toBeFalse();
});

test('a user with a role is staff and not a customer', function () {
    $user = User::factory()->create();

    $user->assignRole('Support');

    expect($user->isStaff())->toBeTrue()
        ->and($user->isCustomer())->toBeFalse();
});

test('the super admin role can perform every seeded permission', function () {
    $user = User::factory()->create();

    $user->assignRole(PermissionSeeder::SUPER_ADMIN);

    expect($user->can('roles.manage'))->toBeTrue()
        ->and($user->can('settings.manage'))->toBeTrue();
});

test('the support role is limited to its granted permissions', function () {
    $user = User::factory()->create();

    $user->assignRole('Support');

    expect($user->can('orders.manage'))->toBeTrue()
        ->and($user->can('settings.manage'))->toBeFalse()
        ->and($user->can('roles.manage'))->toBeFalse();
});

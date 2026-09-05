<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('the reset form answers an unknown address exactly like a known one', function () {
    Notification::fake();

    $known = User::factory()->create();

    $this->post(route('password.email'), ['email' => $known->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $this->post(route('password.email'), ['email' => 'no-such-account@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertSentTo($known, ResetPassword::class);
    Notification::assertCount(1);
});

test('requesting reset links is throttled', function () {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.email'), ['email' => $user->email]);
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertStatus(429);
});

test('resetting a password rotates the remember token so stolen remember-me cookies die', function () {
    Notification::fake();

    $user = User::factory()->create(['remember_token' => 'stolen-remember-token']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Str0ng-Reset-Passw0rd!',
            'password_confirmation' => 'Str0ng-Reset-Passw0rd!',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $user->refresh();

    expect(Hash::check('Str0ng-Reset-Passw0rd!', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('stolen-remember-token');
});

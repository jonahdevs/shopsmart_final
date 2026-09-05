<?php

use App\Services\PaymentCredentials;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;

/**
 * Where the gateway keys come from and whether the store will take money.
 *
 * Settings win over config so an admin can paste live keys in without a
 * redeploy, while `.env` stays how a developer configures a local or CI box.
 *
 * A gateway switched on with no key is not enabled, it is broken: offering it
 * would send the shopper to a checkout that cannot possibly complete.
 */
beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_env_secret');
    config()->set('services.paystack.public_key', 'pk_env_public');
});

test('a stored secret key wins over the configured one', function () {
    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = 'sk_settings_secret';
    $api->save();

    expect(app(PaymentCredentials::class)->paystackSecretKey())->toBe('sk_settings_secret');
});

test('a stored public key wins over the configured one', function () {
    $api = app(PaymentApiSettings::class);
    $api->paystack_public_key = 'pk_settings_public';
    $api->save();

    expect(app(PaymentCredentials::class)->paystackPublicKey())->toBe('pk_settings_public');
});

test('the configured keys are used when no key is stored', function () {
    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = null;
    $api->paystack_public_key = null;
    $api->save();

    $credentials = app(PaymentCredentials::class);

    expect($credentials->paystackSecretKey())->toBe('sk_env_secret')
        ->and($credentials->paystackPublicKey())->toBe('pk_env_public');
});

test('paystack is enabled when the toggle is on and a key is configured', function () {
    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    expect(app(PaymentCredentials::class)->paystackEnabled())->toBeTrue();
});

test('paystack is not enabled when the toggle is on but no key is configured', function () {
    config()->set('services.paystack.secret_key', '');

    $api = app(PaymentApiSettings::class);
    $api->paystack_secret_key = null;
    $api->save();

    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = true;
    $payments->save();

    $credentials = app(PaymentCredentials::class);

    expect($credentials->paystackEnabled())->toBeFalse()
        ->and($credentials->enabledMethods())->not->toContain('paystack');
});

test('paystack is not enabled when the toggle is off even with a key configured', function () {
    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = false;
    $payments->save();

    expect(app(PaymentCredentials::class)->paystackEnabled())->toBeFalse();
});

test('the bank details are read straight off the settings', function () {
    $payments = app(PaymentSettings::class);
    $payments->bank_transfer_enabled = true;
    $payments->bank_details = 'Equity Bank, account 0123456789';
    $payments->save();

    $credentials = app(PaymentCredentials::class);

    expect($credentials->bankTransferEnabled())->toBeTrue()
        ->and($credentials->bankDetails())->toBe('Equity Bank, account 0123456789');
});

test('the enabled methods list reflects the three toggles', function (bool $paystack, bool $bankTransfer, bool $cashOnDelivery, array $expected) {
    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = $paystack;
    $payments->bank_transfer_enabled = $bankTransfer;
    $payments->cash_on_delivery_enabled = $cashOnDelivery;
    $payments->save();

    expect(app(PaymentCredentials::class)->enabledMethods())->toBe($expected);
})->with([
    'all three accepted' => [true, true, true, ['paystack', 'bank_transfer', 'cash_on_delivery']],
    'offline only' => [false, true, true, ['bank_transfer', 'cash_on_delivery']],
    'gateway only' => [true, false, false, ['paystack']],
    'nothing accepted' => [false, false, false, []],
]);

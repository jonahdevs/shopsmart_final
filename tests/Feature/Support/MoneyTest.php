<?php

use App\Settings\CurrencySettings;
use App\Support\Money;

// Guarded by function_exists(), so this is a no-op once composer's
// autoload.files picks the helper up.
require_once __DIR__.'/../../../app/helpers.php';

/**
 * Bind a faked CurrencySettings so Money resolves without touching the
 * settings table.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeCurrency(array $overrides = []): void
{
    CurrencySettings::fake(array_merge([
        'symbol' => 'KES',
        'symbol_position' => 'before',
        'decimals' => 0,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
    ], $overrides));
}

test('it formats cents using the default KES settings', function () {
    fakeCurrency();

    expect(app(Money::class)->format(2400000))->toBe('KES 24,000');
});

test('it places the symbol after the amount when configured', function () {
    fakeCurrency(['symbol_position' => 'after']);

    expect(app(Money::class)->format(2400000))->toBe('24,000 KES');
});

test('it renders the configured number of decimals', function () {
    fakeCurrency(['symbol' => '$', 'decimals' => 2]);

    expect(app(Money::class)->format(2400050))->toBe('$ 24,000.50');
});

test('it honours custom separators', function () {
    fakeCurrency(['symbol' => '€', 'decimals' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',']);

    expect(app(Money::class)->format(2400050))->toBe('€ 24.000,50');
});

test('it joins the symbol with a regular space so the value is clean JSON', function () {
    fakeCurrency();

    $formatted = app(Money::class)->format(100);

    expect($formatted)->toBe('KES 1')
        ->and($formatted)->not->toContain("\u{00A0}")
        ->and(json_decode(json_encode(['price' => $formatted]), true)['price'])->toBe('KES 1');
});

test('the money helper treats null as zero', function () {
    fakeCurrency();

    expect(money(null))->toBe('KES 0')
        ->and(money(0))->toBe('KES 0');
});

test('the money helper accepts float aggregates', function () {
    fakeCurrency();

    expect(money(2400000.0))->toBe('KES 24,000');
});

test('it round trips between major units and storage cents', function () {
    fakeCurrency();

    $money = app(Money::class);

    expect($money->toMinor('24000.50'))->toBe(2400050)
        ->and($money->toMinor(24000.50))->toBe(2400050)
        ->and($money->toMajor(2400050))->toBe(24000.50)
        ->and($money->toMinor($money->toMajor(2400050)))->toBe(2400050);
});

test('toMinor rounds rather than truncating floating point noise', function () {
    fakeCurrency();

    $money = app(Money::class);

    expect($money->toMinor(19.99))->toBe(1999)
        ->and($money->toMinor('0.1'))->toBe(10);
});

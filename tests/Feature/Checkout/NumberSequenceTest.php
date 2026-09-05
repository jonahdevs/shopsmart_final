<?php

use App\Models\NumberSequence;

/**
 * The counter order numbers come out of: it starts at one, never hands the same
 * number out twice, and keeps one series per key.
 */
test('a new series starts at one and counts up', function () {
    expect(NumberSequence::next('order'))->toBe(1)
        ->and(NumberSequence::next('order'))->toBe(2)
        ->and(NumberSequence::next('order'))->toBe(3);
});

test('the first call creates the series row', function () {
    $this->assertDatabaseMissing('number_sequences', ['key' => 'order']);

    NumberSequence::next('order');

    // The row records the NEXT number to hand out, not the one just taken.
    $this->assertDatabaseHas('number_sequences', ['key' => 'order', 'next_value' => 2]);
});

test('two keys keep independent series', function () {
    NumberSequence::next('order');
    NumberSequence::next('order');

    expect(NumberSequence::next('invoice'))->toBe(1)
        ->and(NumberSequence::next('order'))->toBe(3)
        ->and(NumberSequence::next('invoice'))->toBe(2);
});

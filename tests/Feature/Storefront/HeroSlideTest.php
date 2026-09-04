<?php

use App\Models\HeroSlide;
use Illuminate\Support\Facades\Storage;

/**
 * The `live()` scope is the only thing the storefront asks HeroSlide for, so
 * it is what these tests pin down: active, inside its window, in order.
 *
 * The public disk is faked so no test writes real files or queues a conversion.
 */
beforeEach(function () {
    Storage::fake('public');
});

test('an active slide inside its window is live', function () {
    $slide = HeroSlide::factory()
        ->scheduled(now()->subDay(), now()->addDay())
        ->create();

    expect(HeroSlide::live()->pluck('id')->all())->toBe([$slide->id]);
});

test('an inactive slide is not live', function () {
    HeroSlide::factory()->inactive()->create();

    expect(HeroSlide::live()->count())->toBe(0);
});

test('a slide whose window has not opened yet is not live', function () {
    HeroSlide::factory()
        ->scheduled(now()->addDay(), now()->addMonth())
        ->create();

    expect(HeroSlide::live()->count())->toBe(0);
});

test('a slide whose window has closed is not live', function () {
    HeroSlide::factory()->expired()->create();

    expect(HeroSlide::live()->count())->toBe(0);
});

test('a slide with no window is live for as long as it is active', function () {
    $slide = HeroSlide::factory()->active()->create([
        'starts_at' => null,
        'ends_at' => null,
    ]);

    expect(HeroSlide::live()->pluck('id')->all())->toBe([$slide->id]);
});

test('an inactive slide stays hidden even inside its window', function () {
    HeroSlide::factory()
        ->scheduled(now()->subDay(), now()->addDay())
        ->inactive()
        ->create();

    expect(HeroSlide::live()->count())->toBe(0);
});

test('live slides come back in sort order', function () {
    $last = HeroSlide::factory()->active()->create(['sort_order' => 30]);
    $first = HeroSlide::factory()->active()->create(['sort_order' => 10]);
    $middle = HeroSlide::factory()->active()->create(['sort_order' => 20]);

    expect(HeroSlide::live()->pluck('id')->all())
        ->toBe([$first->id, $middle->id, $last->id]);
});

<?php

namespace Database\Seeders\Concerns;

use Carbon\CarbonImmutable;

/**
 * The trading window the demo store's history is spread across.
 *
 * Shared by UserSeeder and OrderSeeder because the two have to agree on it: an
 * order may not predate the customer who placed it, so if registrations and
 * orders were spread over different spans the seeder would spend its time
 * dragging order dates forward to stay legal, and the earliest part of the
 * window would end up with no trading at all.
 */
trait SeedsDemoHistory
{
    /** How far back the demo store's history runs. */
    public const WINDOW_DAYS = 120;

    /**
     * A point in the window, weighted toward the recent end.
     *
     * Squaring a uniform random pulls the distribution toward "now", which is
     * what a growing store looks like — and it means the current period
     * genuinely outperforms the previous one, so the trend arrows on the
     * dashboard point somewhere rather than hovering at zero.
     */
    protected function momentInWindow(): CarbonImmutable
    {
        $skewed = fake()->randomFloat(4, 0, 1) ** 2;

        return now()
            ->subDays((int) round($skewed * self::WINDOW_DAYS))
            ->setTime(fake()->numberBetween(7, 21), fake()->numberBetween(0, 59));
    }
}

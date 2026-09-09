<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The tiles above the admin orders table.
 *
 * Two of them are queues and two are trade. The queues are counts of a set the
 * table itself can show, so each carries the filter that produces it — a tile
 * reading "Awaiting payment: 9" that a staff member cannot click through to the
 * nine orders has told them a number and denied them the work.
 *
 * The trading pair is measured over a trailing window against the window before
 * it, the same shape the overview uses, so "revenue" means the same span of
 * days on both sides of the comparison. Revenue counts PAID orders only: an
 * order placed but never collected is not money the store has.
 *
 * These figures describe the whole store, not the filtered page beneath them.
 * That is deliberate — the tiles are the context a staff member filters
 * *against*, and recomputing them per filter would make the queue counts move
 * every time somebody typed in the search box.
 */
#[TypeScript]
class AdminOrderStatsData extends Data
{
    public function __construct(
        /** Orders whose payment has not been collected. */
        public int $awaitingPaymentCount,
        /** Orders being picked and packed. */
        public int $awaitingFulfilmentCount,
        public int $revenueCents,
        public string $revenueFormatted,
        public ?float $revenueChangePercent,
        public int $averageOrderValueCents,
        public string $averageOrderValueFormatted,
        public ?float $averageOrderValueChangePercent,
        /** The window the trading figures cover, e.g. "Last 30 days". */
        public string $periodLabel,
    ) {}
}

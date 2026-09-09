<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The tiles above the admin products table.
 *
 * All four are counts of a set the table itself can show, so every one carries
 * the filter that produces it — a tile reading "Out of stock: 12" that a staff
 * member cannot click through to the twelve products has told them a number and
 * denied them the work.
 *
 * There is no trading window here and so no delta. A catalog does not have a
 * "last thirty days" the way revenue does: a manager asks how many products are
 * live, not how many became live this month, and a percentage against last
 * month would be arithmetic dressed up as insight.
 *
 * Every figure counts live rows only — the bin is excluded, exactly as the
 * table's own default view excludes it. The two stock figures overlap by
 * design: a product can be both out of stock and under its threshold, and each
 * tile matches its own filter rather than a share of one total.
 *
 * These figures describe the whole catalog, not the filtered page beneath them.
 * That is deliberate — the tiles are the context a staff member filters
 * *against*, and recomputing them per filter would make every count move the
 * moment somebody typed in the search box.
 */
#[TypeScript]
class AdminProductStatsData extends Data
{
    public function __construct(
        /** Products on sale now. */
        public int $publishedCount,
        /** Products still being written, invisible to shoppers. */
        public int $draftCount,
        /** Stock-tracked products at or below the store's low-stock threshold. */
        public int $lowStockCount,
        /** Products marked out of stock, tracked or not. */
        public int $outOfStockCount,
        /** The threshold behind `lowStockCount`, so the tile can name it. */
        public int $lowStockThreshold,
    ) {}
}

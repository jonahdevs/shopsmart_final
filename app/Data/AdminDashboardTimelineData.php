<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The trading series behind the dashboard's main chart and its sparklines.
 *
 * Parallel arrays rather than a list of points: every series shares one set of
 * buckets, and a charting library wants `{ categories, series }` in exactly
 * this shape. Sending a list of objects would mean the client pivoting it back
 * on every render.
 *
 * Revenue is in MAJOR units here, unlike almost everything else in the app.
 * A chart axis labelled in cents is unreadable and the tooltip formats itself
 * from {@see AdminChartSliceData}'s convention anyway — but it means this is
 * the one place a `_cents` suffix would be a lie, so there isn't one.
 *
 * Buckets are always contiguous and zero-filled. A day with no orders has to
 * appear as a zero, because a line that simply skips it draws a slope between
 * two distant days and invents trading that did not happen.
 *
 * `averageOrder` exists so the fourth headline tile has a silhouette like the
 * other three. A tile row where one card carries a sparkline floor and its
 * neighbour does not renders at two different heights under a stretching grid,
 * and the odd one out reads as broken rather than as "this metric has no
 * shape". A quiet day averages 0, for the same reason its revenue does.
 */
#[TypeScript]
class AdminDashboardTimelineData extends Data
{
    /**
     * @param  list<string>  $labels  Bucket labels, already formatted for the axis.
     * @param  list<float>  $revenue  Revenue per bucket, in major currency units.
     * @param  list<int>  $orders  Paid orders per bucket.
     * @param  list<int>  $customers  Customers who registered in each bucket.
     * @param  list<float>  $averageOrder  Mean paid order value per bucket, in major units.
     * @param  string  $currencySymbol  Prefix for a money axis, e.g. "KES" — never assembled client-side.
     */
    public function __construct(
        public array $labels,
        public array $revenue,
        public array $orders,
        public array $customers,
        public array $averageOrder,
        public string $currencySymbol,
    ) {}
}

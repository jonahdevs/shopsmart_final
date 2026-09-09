<?php

namespace App\Data;

use Inertia\Inertia;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the dashboard draws, in one deferred prop.
 *
 * Grouped deliberately. The tiles above answer "how are we doing" off cheap
 * aggregates and must paint immediately; these six breakdowns each cost a
 * grouped scan and together are the slowest thing on the page. Bundling them
 * lets the controller defer the whole set behind one {@see Inertia::defer()}
 * so the tiles are not held hostage to the charts.
 *
 * Every series is capped server-side. "Top products" is six rows because a
 * radial chart with forty arms is a decoration, not a report.
 *
 * Two slice shapes travel here, and the difference is the panel's form rather
 * than its subject. Anything the page plots on an axis is an
 * {@see AdminChartSliceData}; anything it draws as a labelled meter is an
 * {@see AdminDashboardShareSliceData}, which carries the share as a figure
 * because a meter has no axis to read it off.
 *
 * The method split carries its own total as well as its slices, because the
 * donut states that total in its hole. Borrowing the revenue tile's figure
 * would look identical today and is the wrong seam: the tile is a separate
 * aggregate over a window this object does not own, so the day the two windows
 * diverge the hole would contradict the slices printed underneath it.
 */
#[TypeScript]
class AdminDashboardChartsData extends Data
{
    /**
     * @param  list<AdminDashboardShareSliceData>  $ordersByStatus  Orders in the window, split by status.
     * @param  list<AdminDashboardShareSliceData>  $revenueByMethod  Captured revenue, split by how it was paid.
     * @param  string  $revenueByMethodTotalFormatted  The sum of those slices, which the donut prints in its hole.
     * @param  list<AdminChartSliceData>  $topProducts  Best sellers by units shipped.
     * @param  list<AdminChartSliceData>  $topCategories  Best selling categories by units shipped.
     * @param  list<AdminDashboardShareSliceData>  $ratings  Approved reviews per star, five down to one.
     * @param  float|null  $averageRating  Mean approved rating, or null when nothing has been reviewed.
     */
    public function __construct(
        public AdminDashboardTimelineData $timeline,
        public array $ordersByStatus,
        public array $revenueByMethod,
        public string $revenueByMethodTotalFormatted,
        public array $topProducts,
        public array $topCategories,
        public array $ratings,
        public ?float $averageRating,
        public int $reviewCount,
    ) {}
}

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
 */
#[TypeScript]
class AdminDashboardChartsData extends Data
{
    /**
     * @param  list<AdminChartSliceData>  $ordersByStatus  Orders in the window, split by status.
     * @param  list<AdminChartSliceData>  $revenueByMethod  Captured revenue, split by how it was paid.
     * @param  list<AdminChartSliceData>  $topProducts  Best sellers by units shipped.
     * @param  list<AdminChartSliceData>  $topCategories  Best selling categories by units shipped.
     * @param  list<AdminChartSliceData>  $ratings  Approved reviews per star, five down to one.
     * @param  float|null  $averageRating  Mean approved rating, or null when nothing has been reviewed.
     */
    public function __construct(
        public AdminDashboardTimelineData $timeline,
        public array $ordersByStatus,
        public array $revenueByMethod,
        public array $topProducts,
        public array $topCategories,
        public array $ratings,
        public ?float $averageRating,
        public int $reviewCount,
    ) {}
}

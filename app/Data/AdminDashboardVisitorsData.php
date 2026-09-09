<?php

namespace App\Data;

use App\Http\Middleware\TrackVisitor;
use Inertia\Inertia;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who came to the shop in the window, split two ways.
 *
 * Kept out of {@see AdminDashboardChartsData} on purpose. That object is the
 * trading breakdowns, all of which come from `orders`; this comes from
 * `visitors`, a table that only exists for the visitors who granted analytics
 * consent (see {@see TrackVisitor}) and can therefore be legitimately empty on
 * a store that offers no consent banner at all. Its own {@see Inertia::defer()}
 * prop means the charts do not wait on it and it does not wait on the charts.
 *
 * Neither breakdown is plotted on an axis — one is drawn as a bar's segment
 * widths, the other as fills on a map — so both carry a share computed here.
 * Nothing on this page divides anything out in the browser.
 *
 * The two shares answer to different denominators, and that is deliberate: a
 * platform is read off every user-agent string, so its total is the window's
 * sessions, while a country is only known for the sessions an edge could place.
 * Shading the map against sessions it has no country for would make every
 * country look quieter than it is, so {@see AdminDashboardCountrySliceData}
 * divides by the placed sessions instead. `sessionCount` is the number the
 * platform panel is a fraction of; the map states its own total.
 */
#[TypeScript]
class AdminDashboardVisitorsData extends Data
{
    /**
     * @param  int  $sessionCount  Browsing sessions in the window — visits, not page views.
     * @param  int  $newCount  Sessions from a browser the shop had not seen before.
     * @param  int  $returningCount  Sessions from a browser that had been here already.
     * @param  list<AdminDashboardShareSliceData>  $platforms  Sessions by operating system, largest first.
     * @param  list<AdminDashboardCountrySliceData>  $countries  Sessions by country, largest first. Only the sessions an edge could place.
     */
    public function __construct(
        public int $sessionCount,
        public int $newCount,
        public int $returningCount,
        public array $platforms,
        public array $countries,
    ) {}
}

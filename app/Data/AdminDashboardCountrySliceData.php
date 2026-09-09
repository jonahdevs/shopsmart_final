<?php

namespace App\Data;

use App\Support\Http\VisitorCountry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One country on the visitors map.
 *
 * Neither of the other breakdown rows fits. {@see AdminChartSliceData} carries
 * a label and a plottable number, and {@see AdminDashboardShareSliceData} adds
 * the share a meter needs — but a map is addressed by region code, and the code
 * is the one field neither of them has. A label cannot stand in for it: the map
 * knows "KE", not "Kenya", and it is the server that decides which of those a
 * human reads.
 *
 * `count` is a plain integer rather than the `float $value` a chart slice
 * carries, because the map shades regions from these numbers directly and an
 * axis is never laid out from them.
 */
#[TypeScript]
class AdminDashboardCountrySliceData extends Data
{
    public function __construct(
        /** ISO 3166-1 alpha-2, uppercase — the map's own region key. */
        public string $code,
        /** The country's name, resolved server-side. */
        public string $label,
        public int $count,
        /** What a human reads — separators applied by the server. */
        public string $formatted,
        /** This country's percentage of the placed sessions, 0–100. */
        public float $share,
    ) {}

    /**
     * A country's row, given its own session count and the total across every
     * country the edge could place.
     */
    public static function make(string $code, int $count, int $placed): self
    {
        return new self(
            code: $code,
            label: VisitorCountry::name($code),
            count: $count,
            formatted: number_format($count),
            share: $placed > 0 ? round(($count / $placed) * 100, 1) : 0.0,
        );
    }
}

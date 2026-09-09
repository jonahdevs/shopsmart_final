<?php

namespace App\Data;

use App\Enums\OrderStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of a breakdown the dashboard draws as a meter rather than as a chart.
 *
 * {@see AdminChartSliceData} is enough for a plotted series: a number to lay an
 * axis out from and a string for the tooltip. A meter has no axis, so the reader
 * takes the proportion off the bar's width and the exact figure off the text
 * beside it — which means the share has to be a real, server-computed number
 * rather than a width the client divides out. Putting it here keeps the one rule
 * the whole app follows: the browser renders figures, it never derives them.
 *
 * `variant` is the enum's own badge variant, present only where the rows are
 * statuses. It travels with the row so the status ladder tints itself from
 * `app/Enums` exactly as every table on the site does — see
 * {@see OrderStatus::badgeVariant()} — instead of the page learning a
 * second mapping that can drift.
 */
#[TypeScript]
class AdminDashboardShareSliceData extends Data
{
    public function __construct(
        public string $label,
        /** Plottable: major units for money, a plain count otherwise. */
        public float $value,
        /** What a human reads — currency and separators applied by the server. */
        public string $formatted,
        /** This row's percentage of the breakdown's total, 0–100. */
        public float $share,
        /** shadcn-vue badge variant, when the row names a status. */
        public ?string $variant = null,
    ) {}

    /**
     * A row measuring a count of things.
     */
    public static function count(string $label, int $count, int $total, ?string $variant = null): self
    {
        return new self(
            label: $label,
            value: $count,
            formatted: number_format($count),
            share: self::share($count, $total),
            variant: $variant,
        );
    }

    /**
     * A row measuring money, plotted in major units.
     */
    public static function money(string $label, int $cents, int $totalCents): self
    {
        return new self(
            label: $label,
            value: round($cents / 100, 2),
            formatted: money($cents),
            share: self::share($cents, $totalCents),
        );
    }

    /**
     * A share of nothing is 0, not a division by zero: an empty breakdown still
     * has to render its rows so the reader sees "no orders in this status"
     * rather than a panel that quietly lost its statuses.
     */
    private static function share(int|float $value, int|float $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}

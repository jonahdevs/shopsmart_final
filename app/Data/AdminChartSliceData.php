<?php

namespace App\Data;

use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One labelled value in a breakdown chart.
 *
 * The value crosses the wire twice, for the same reason every price does: the
 * chart needs a number it can plot and lay out an axis from, and the tooltip
 * needs the store's own formatting. Letting the client turn 1_250_000 into
 * "KES 12,500" would put the currency symbol, the separators and the decimal
 * places in a second place that can disagree with {@see Money}.
 *
 * `value` is therefore always plottable — major units for money, a plain count
 * otherwise — and `formatted` is always what a human should read.
 */
#[TypeScript]
class AdminChartSliceData extends Data
{
    public function __construct(
        public string $label,
        public float $value,
        public string $formatted,
    ) {}

    /**
     * A slice measuring money, plotted in major units.
     */
    public static function money(string $label, int $cents): self
    {
        return new self(
            label: $label,
            value: round($cents / 100, 2),
            formatted: money($cents),
        );
    }

    /**
     * A slice measuring a count of things.
     */
    public static function count(string $label, int $count, ?string $suffix = null): self
    {
        return new self(
            label: $label,
            value: $count,
            formatted: $suffix === null
                ? number_format($count)
                : number_format($count).' '.$suffix,
        );
    }
}

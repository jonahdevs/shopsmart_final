<?php

namespace App\Data;

use App\Enums\DateRangePreset;
use App\Support\DateRange;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state of one date range control, and everything it needs to draw itself.
 *
 * The server owns the preset list and the resolved window; the client owns only
 * the gesture. That split is what keeps a shared link honest — the URL carries
 * `range=last_30_days`, and what those thirty days are is decided here, on the
 * day the link is opened, rather than baked into the link when it was made.
 *
 * `preset` is an empty string when no range is applied at all. That is a real
 * state on an index screen, where "all time" is the default, and it is why this
 * carries a string rather than the enum: there is no enum case for "none", and
 * inventing one would put a window with no dates into a type whose whole job is
 * to have them.
 *
 * `start` and `end` are plain `Y-m-d` because they are what a calendar widget
 * highlights, not something the client computes with.
 */
#[TypeScript]
class AdminDateRangeData extends Data
{
    /**
     * @param  list<AdminDateRangeOptionData>  $presets
     */
    public function __construct(
        public string $preset,
        public ?string $start,
        public ?string $end,
        public string $label,
        public array $presets,
    ) {}

    /**
     * @param  string  $emptyLabel  What the control reads when no range applies.
     */
    public static function fromRange(?DateRange $range, string $emptyLabel = 'All time'): self
    {
        return new self(
            preset: $range?->preset->value ?? '',
            start: $range?->start()->toDateString(),
            end: $range?->end()->toDateString(),
            label: $range?->label() ?? $emptyLabel,
            presets: self::presets(),
        );
    }

    /**
     * @return list<AdminDateRangeOptionData>
     */
    private static function presets(): array
    {
        return array_values(array_map(
            static fn (DateRangePreset $preset): AdminDateRangeOptionData => AdminDateRangeOptionData::fromPreset($preset),
            DateRangePreset::presets(),
        ));
    }
}

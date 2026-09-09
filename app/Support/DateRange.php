<?php

namespace App\Support;

use App\Enums\DateRangePreset;
use Illuminate\Support\Carbon;

/**
 * A resolved reporting window, and the one place that knows what a preset means.
 *
 * Every admin screen that filters by date sends a preset KEY rather than two
 * dates, so a bookmarked "last 30 days" is still the last thirty days a month
 * from now. That only works if exactly one definition of each preset exists,
 * which is what this class is: the enum names the windows, this resolves them.
 *
 * A window is half-open at neither end — `start` is the first instant of the
 * first day and `end` the last instant of the last — so `whereBetween` on a
 * timestamp column is inclusive of both days a reader named.
 *
 * Carbon is mutable, and a value object handing out its own instances is a
 * value object waiting to be mutated by a caller's `->addDay()`. So the dates
 * are private and {@see self::start()} and {@see self::end()} hand out copies.
 */
final class DateRange
{
    /**
     * The longest window a custom range may span.
     *
     * A year and a day, so a full calendar year fits either side of a leap day.
     * The bound exists because the query string is public: every panel on the
     * dashboard is an aggregate over this window, and without a ceiling a
     * crafted `?from=1970-01-01` turns each of them into a full table scan. It
     * is also the point past which the trading chart stops being readable — it
     * plots one point per day, and no screen holds four hundred of them.
     */
    public const MAX_SPAN_DAYS = 366;

    private function __construct(
        public readonly DateRangePreset $preset,
        private readonly Carbon $start,
        private readonly Carbon $end,
    ) {}

    /**
     * The window a request asked for, or null when it asked for none.
     *
     * Null is "all time" and is a real answer, not a failure: an index screen
     * opens unfiltered and an auditor arrives with a question, not a form. The
     * dashboard, which has no meaningful all-time state, supplies its own
     * default instead — see {@see self::preset()}.
     */
    public static function fromRequest(?string $preset, ?string $from, ?string $to): ?self
    {
        $case = $preset === null || $preset === ''
            ? null
            : DateRangePreset::tryFrom($preset);

        if ($case === DateRangePreset::Custom || ($case === null && ($from !== null || $to !== null))) {
            return self::custom($from, $to);
        }

        return $case === null ? null : self::preset($case);
    }

    /**
     * The window a named preset resolves to, measured from now.
     *
     * The trailing presets count today as one of their days — "last 7 days"
     * means this day and the six before it, which is what a reader means by it
     * and what makes two consecutive readings a week apart.
     */
    public static function preset(DateRangePreset $preset): self
    {
        $now = Carbon::now();

        return match ($preset) {
            DateRangePreset::Today => new self($preset, $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            DateRangePreset::Yesterday => new self(
                $preset,
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ),
            // `startOfWeek` follows the app's configured first day, so "this
            // week" means the same thing here as it does on a calendar the
            // reader is looking at.
            DateRangePreset::ThisWeek => new self($preset, $now->copy()->startOfWeek(), $now->copy()->endOfDay()),
            DateRangePreset::Last7Days => new self($preset, $now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()),
            DateRangePreset::ThisMonth => new self($preset, $now->copy()->startOfMonth(), $now->copy()->endOfDay()),
            DateRangePreset::LastMonth => new self(
                $preset,
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ),
            DateRangePreset::ThisYear => new self($preset, $now->copy()->startOfYear(), $now->copy()->endOfDay()),
            DateRangePreset::LastYear => new self(
                $preset,
                $now->copy()->subYearNoOverflow()->startOfYear(),
                $now->copy()->subYearNoOverflow()->endOfYear(),
            ),
            // Reached only if a caller asks for the case the picker cannot
            // produce; the default window is the honest fallback.
            DateRangePreset::Custom => self::preset(DateRangePreset::ThisWeek),
        };
    }

    /**
     * A window a reader drew on the calendar.
     *
     * An open end is closed onto today rather than left unbounded, because the
     * span ceiling can only be enforced against two real dates and an unbounded
     * window is exactly what the ceiling exists to prevent. Validation has
     * already rejected an inverted or over-long pair by the time this runs.
     */
    public static function custom(?string $from, ?string $to): self
    {
        $today = Carbon::now();

        $start = $from === null || $from === ''
            ? $today->copy()->subDays(29)->startOfDay()
            : Carbon::parse($from)->startOfDay();

        $end = $to === null || $to === ''
            ? $today->copy()->endOfDay()
            : Carbon::parse($to)->endOfDay();

        return new self(DateRangePreset::Custom, $start, $end);
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function end(): Carbon
    {
        return $this->end->copy();
    }

    /**
     * The window of equal length immediately before this one.
     *
     * Equal length rather than "the same calendar month before" so a delta is
     * always a comparison of like with like — a thirty-day window compared
     * against a thirty-one-day February would move the percentage on its own.
     */
    public function previous(): self
    {
        $length = $this->start->diffInSeconds($this->end);

        return new self(
            $this->preset,
            $this->start->copy()->subSeconds($length),
            $this->start->copy(),
        );
    }

    /** Whole days covered, counting both end days. */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /** What the screen calls this window: the preset's name, or the dates. */
    public function label(): string
    {
        if ($this->preset !== DateRangePreset::Custom) {
            return $this->preset->label();
        }

        if ($this->start->isSameDay($this->end)) {
            return $this->start->format('j M Y');
        }

        return $this->start->format('j M Y').' – '.$this->end->format('j M Y');
    }

    /** What a period-on-period delta on this window is measured against. */
    public function comparisonLabel(): string
    {
        return __('vs previous :days days', ['days' => $this->days()]);
    }
}

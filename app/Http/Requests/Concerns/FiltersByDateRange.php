<?php

namespace App\Http\Requests\Concerns;

use App\Enums\DateRangePreset;
use App\Support\DateRange;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The three query parameters every date-filtered admin screen shares.
 *
 * `range` is a preset key, `from` and `to` are a custom window. They are
 * validated in one place because the interesting rule is not that each is a
 * date — it is that the pair they form is one a server should be willing to
 * aggregate over.
 *
 * Three things are checked, and each one is a way the query string could be
 * abused or simply be wrong:
 *
 * - `range` against the closed enum, because an unknown key would otherwise
 *   fall through to a default and quietly report a different window than the
 *   URL claims.
 * - `to` on or after `from`, because an inverted pair silently matches nothing
 *   and reads as "there is no data" rather than "you asked for no days".
 * - the span against {@see DateRange::MAX_SPAN_DAYS}, because every screen this
 *   trait serves runs unbounded aggregates over the window, and the window
 *   comes from a public query string.
 */
trait FiltersByDateRange
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function dateRangeRules(): array
    {
        return [
            'range' => ['nullable', Rule::enum(DateRangePreset::class)],
            // `bail` so the span check below only ever sees a parseable date;
            // without it a junk `from` reaches Carbon::parse and throws a 500
            // where a 422 was the whole point.
            'from' => ['bail', 'nullable', 'date', $this->withinMaxSpan()],
            'to' => ['bail', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * The window this request asked for, or null when it asked for none.
     */
    public function dateRange(): ?DateRange
    {
        return DateRange::fromRequest(
            $this->validated('range'),
            $this->validated('from'),
            $this->validated('to'),
        );
    }

    /**
     * Rejects a custom window longer than the ceiling.
     *
     * Attached to `from` rather than `to` because an open-ended window — a
     * start with no end — is the one that runs to today and is therefore the
     * one that can be made arbitrarily long. A `to` with no `from` cannot: the
     * start falls back to a bounded default.
     */
    private function withinMaxSpan(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $to = $this->input('to');

            $start = Carbon::parse($value)->startOfDay();
            $end = is_string($to) && $to !== ''
                ? Carbon::parse($to)->endOfDay()
                : Carbon::now()->endOfDay();

            // Cast before adding: the end is the last instant of its day, so
            // the difference is a fraction under a whole number of days and a
            // float comparison would reject a window exactly at the ceiling.
            if ((int) $start->diffInDays($end) + 1 > DateRange::MAX_SPAN_DAYS) {
                $fail(__('The date range may not be longer than :days days.', [
                    'days' => DateRange::MAX_SPAN_DAYS,
                ]));
            }
        };
    }
}

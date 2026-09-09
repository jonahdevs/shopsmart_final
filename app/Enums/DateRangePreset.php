<?php

namespace App\Enums;

use App\Support\DateRange;

/**
 * The named windows an admin screen may be filtered to.
 *
 * A closed list, and that is the point: the client sends a key, not two
 * resolved dates, so a link to "last 7 days" still means the last seven days
 * tomorrow. It also means the only date arithmetic in the system lives on the
 * server, where one definition of "this month" can serve every screen.
 *
 * The set matches the reference build's, which pairs each period with the one
 * before it — today/yesterday, this month/last month, this year/last year — so
 * a reader can always answer "and how did that compare?" without drawing a
 * custom window. Rolling windows longer than a week were dropped for the same
 * reason: "last 30 days" has no companion, and the calendar covers the case.
 *
 * {@see DateRange} turns a case into an actual window.
 */
enum DateRangePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case Last7Days = 'last_7_days';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => __('Today'),
            self::Yesterday => __('Yesterday'),
            self::ThisWeek => __('This week'),
            self::Last7Days => __('Last 7 days'),
            self::ThisMonth => __('This month'),
            self::LastMonth => __('Last month'),
            self::ThisYear => __('This year'),
            self::LastYear => __('Last year'),
            self::Custom => __('Custom range'),
        };
    }

    /**
     * The cases a picker offers as buttons.
     *
     * `Custom` is excluded because it is not a choice a preset list can make —
     * it is what the calendar beside the list produces, and a button that
     * resolved to no particular window would do nothing when pressed.
     *
     * @return list<self>
     */
    public static function presets(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $preset): bool => $preset !== self::Custom,
        ));
    }
}

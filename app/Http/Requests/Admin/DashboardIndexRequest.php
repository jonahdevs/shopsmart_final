<?php

namespace App\Http\Requests\Admin;

use App\Enums\DateRangePreset;
use App\Http\Requests\Concerns\FiltersByDateRange;
use App\Support\DateRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The overview's date range, validated.
 *
 * The dashboard has no "all time" state — every figure on it is a period result
 * compared against the period before it, and there is no period before all of
 * time. So an empty query string resolves to {@see self::DEFAULT_RANGE} rather
 * than to no window, which is also what the page reported before it had a
 * picker at all.
 */
class DashboardIndexRequest extends FormRequest
{
    use FiltersByDateRange;

    /** The window the page opens on when the URL names none. */
    public const DEFAULT_RANGE = DateRangePreset::ThisWeek;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->dateRangeRules();
    }

    public function range(): DateRange
    {
        return $this->dateRange() ?? DateRange::preset(self::DEFAULT_RANGE);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminActivityRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityIndexRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit trail.
 *
 * Read-only, and not by omission: there is deliberately no store, update or
 * destroy on this controller and no route that could reach one. A log staff can
 * edit is not evidence of anything, and the whole value of this page is that
 * what it says happened is what happened. Entries age out through
 * `activitylog.clean_after_days` and an artisan command, never through a button.
 *
 * The trail is itself personal data — it records staff actions against customer
 * orders and payments — so `activity.view` buys you the shape of an event, not
 * automatically its contents. {@see AdminActivityRowData} hides the before and
 * after values from a viewer who lacks the permission that governs the subject,
 * and treats an unrecognised subject type as secret rather than public.
 */
class ActivityController extends Controller
{
    /** Rows per page in the trail. */
    private const PER_PAGE = 25;

    public function __invoke(ActivityIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            // The trail is written many times a second in a busy hour, so equal
            // timestamps are ordinary. Without a tiebreaker the same row can
            // appear on two pages and another on none.
            ->orderBy('id', $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $visibility = $this->subjectVisibility($request->user());

        return Inertia::render('admin/activity/Index', [
            'entries' => array_values(array_map(
                fn (Activity $activity): AdminActivityRowData => AdminActivityRowData::fromModel(
                    $activity,
                    $visibility[$activity->subject_type] ?? false,
                ),
                $activities->items(),
            )),
            'pagination' => PaginationData::fromPaginator($activities),
            'filters' => [
                'log_name' => $request->validated('log_name'),
                'event' => $request->validated('event'),
                'subject_type' => $request->validated('subject_type'),
                'causer_id' => $request->validated('causer_id'),
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'logNames' => $this->distinct('log_name'),
            'events' => $this->distinct('event'),
            'subjectTypes' => $this->subjectTypes(),
            'causers' => $this->causers(),
        ]);
    }

    /**
     * Which subjects this viewer may see the values of.
     *
     * Keyed by the class name stored on the row, so the lookup in the loop above
     * is a plain array read rather than a permission check per entry.
     *
     * @return array<string, bool>
     */
    private function subjectVisibility(?object $viewer): array
    {
        $visibility = [];

        foreach (AdminActivityRowData::SUBJECT_PERMISSIONS as $subject => $permission) {
            $visibility[$subject] = $viewer instanceof User && $viewer->can($permission);
        }

        return $visibility;
    }

    /**
     * The values a filter can usefully offer — the ones actually in the table.
     *
     * @param  'log_name'|'event'  $column
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return array_values(array_map(
            static fn (mixed $value): string => (string) $value,
            Activity::query()
                ->whereNotNull($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->all(),
        ));
    }

    /**
     * The subject types present, with the class name kept for the filter and a
     * basename for the label — the page never shows a fully qualified class.
     *
     * @return list<array{value: string, label: string}>
     */
    private function subjectTypes(): array
    {
        return array_values(array_map(
            static fn (mixed $type): array => [
                'value' => (string) $type,
                'label' => class_basename((string) $type),
            ],
            Activity::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->all(),
        ));
    }

    /**
     * The people who appear in the trail, for the "who" filter.
     *
     * @return list<array{value: int, label: string}>
     */
    private function causers(): array
    {
        $ids = Activity::query()
            ->where('causer_type', (new User)->getMorphClass())
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id')
            ->all();

        return array_values(User::query()
            ->whereKey($ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'value' => $user->getKey(),
                'label' => $user->name,
            ])
            ->all());
    }

    /**
     * @param  Builder<Activity>  $query
     */
    private function applyFilters(Builder $query, ActivityIndexRequest $request): void
    {
        $query
            ->when($request->validated('log_name'), fn (Builder $q, string $log) => $q->where('log_name', $log))
            ->when($request->validated('event'), fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($request->validated('subject_type'), fn (Builder $q, string $type) => $q->where('subject_type', $type))
            // A query string arrives as text even after an `integer` rule, so
            // the parameter is typed for what actually turns up.
            ->when($request->validated('causer_id'), fn (Builder $q, int|string $causer) => $q
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', (int) $causer))
            ->when($request->validated('from'), fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->validated('to'), fn (Builder $q, string $to) => $q->whereDate('created_at', '<=', $to));
    }
}

<?php

use App\Models\Order;
use App\Models\User;
use App\Support\DateRange;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * The shared reporting window.
 *
 * Two things are being protected here. The first is that a preset means the
 * same thing everywhere and keeps meaning it: the URL carries a key, the server
 * resolves it on the day the link is opened, and a bookmark from last month is
 * still a rolling window rather than a frozen pair of dates.
 *
 * The second is that the window comes from a public query string and every
 * panel it governs is an unbounded aggregate. An unknown key, an inverted pair
 * and a decade-long span all have to be refused before they reach a scan.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('the dashboard opens on this week when the url names no range', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('range.preset', 'this_week')
                ->where('range.start', '2026-09-07')
                ->where('range.end', '2026-09-09')
                ->where('stats.periodLabel', 'This week')
        );
});

test('each preset resolves the window its label claims', function (string $preset, string $start, string $end) {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => $preset]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('range.preset', $preset)
                ->where('range.start', $start)
                ->where('range.end', $end)
        );
})->with([
    // Every trailing window counts today as one of its days: "last 7 days"
    // means this day and the six before it, which is what a reader means by it.
    ['today', '2026-09-09', '2026-09-09'],
    // 2026-09-09 is a Wednesday; the week opens on the Monday.
    ['this_week', '2026-09-07', '2026-09-09'],
    ['last_7_days', '2026-09-03', '2026-09-09'],
    ['this_month', '2026-09-01', '2026-09-09'],
    ['this_year', '2026-01-01', '2026-09-09'],
    // The presets that end in the past. Each is a whole period rather than one
    // ending today, or it would overlap the period it is meant to be compared
    // against.
    ['yesterday', '2026-09-08', '2026-09-08'],
    ['last_month', '2026-08-01', '2026-08-31'],
    ['last_year', '2025-01-01', '2025-12-31'],
]);

test('a preset governs which orders the dashboard counts as revenue', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    Order::factory()->paid()->create([
        'total_cents' => 150_000,
        'paid_at' => Carbon::parse('2026-09-08 09:00:00'),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 900_000,
        'paid_at' => Carbon::parse('2026-08-20 09:00:00'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'last_7_days']))
        ->assertInertia(fn ($page) => $page->where('stats.revenueCents', 150_000));

    // The same store, a wider window: both orders now count. August is outside
    // every month-or-shorter preset, so the year is what proves the second
    // order was excluded by the window rather than missing from the fixture.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'this_year']))
        ->assertInertia(fn ($page) => $page->where('stats.revenueCents', 1_050_000));
});

test('a custom range is inclusive of both the days it names', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    // Late on the last day and at midnight on the first: a window built from
    // whole dates has to cover both, or a reader loses a day at each end.
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'paid_at' => Carbon::parse('2026-09-01 00:00:00'),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 200_000,
        'paid_at' => Carbon::parse('2026-09-03 23:59:00'),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 400_000,
        'paid_at' => Carbon::parse('2026-09-04 00:30:00'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', [
            'range' => 'custom',
            'from' => '2026-09-01',
            'to' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.revenueCents', 300_000)
                ->where('range.preset', 'custom')
                ->where('range.start', '2026-09-01')
                ->where('range.end', '2026-09-03')
        );
});

test('an unknown preset key is refused rather than falling back to a default', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'last_decade']))
        ->assertSessionHasErrors('range');
});

test('a range that ends before it starts is refused', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', [
            'range' => 'custom',
            'from' => '2026-09-09',
            'to' => '2026-09-01',
        ]))
        ->assertSessionHasErrors('to');
});

test('a custom range longer than the ceiling is refused', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $from = Carbon::parse('2026-09-09')->subDays(DateRange::MAX_SPAN_DAYS)->toDateString();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', [
            'range' => 'custom',
            'from' => $from,
            'to' => '2026-09-09',
        ]))
        ->assertSessionHasErrors('from');
});

test('an open-ended custom range is measured against today and refused when too long', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    // No `to` at all: the window runs to today, which is exactly the shape a
    // crafted request would use to aggregate the whole table.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'custom', 'from' => '2015-01-01']))
        ->assertSessionHasErrors('from');
});

test('a range at the ceiling is allowed', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $from = Carbon::parse('2026-09-09')->subDays(DateRange::MAX_SPAN_DAYS - 1)->toDateString();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', [
            'range' => 'custom',
            'from' => $from,
            'to' => '2026-09-09',
        ]))
        ->assertOk()
        ->assertSessionHasNoErrors();
});

test('the queue counters ignore the range because they are not a period result', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    // Placed long before any window the picker offers, and still waiting.
    Order::factory()->create(['placed_at' => Carbon::parse('2024-01-01 09:00:00')]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'today']))
        ->assertInertia(fn ($page) => $page->where('stats.awaitingPaymentCount', 1));
});

test('the audit trail is all time until a range is asked for', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $order = Order::factory()->create();
    $order->update(['staff_note' => 'Called the customer.']);

    Activity::query()->update(['created_at' => Carbon::parse('2026-07-01 09:00:00')]);

    $this->actingAs($this->admin)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('dateRange.preset', '')
                ->where('dateRange.start', null)
                ->where('dateRange.label', 'All time')
                // Two: the order's own `created` event and the note `updated`.
                ->has('entries', 2)
        );
});

test('a preset narrows the audit trail and stays a preset in the response', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $order = Order::factory()->create();
    $order->update(['staff_note' => 'Called the customer.']);

    Activity::query()->update(['created_at' => Carbon::parse('2026-07-01 09:00:00')]);

    $this->actingAs($this->admin)
        ->get(route('admin.activity.index', ['range' => 'last_7_days']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('entries', 0)
                // The key, not the dates it resolved to: that is what makes the
                // filtered URL still mean "last 7 days" tomorrow.
                ->where('filters.range', 'last_7_days')
                ->where('filters.from', null)
                ->where('dateRange.start', '2026-09-03')
        );
});

test('a custom range narrows the audit trail', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    $order = Order::factory()->create();
    $order->update(['staff_note' => 'Called the customer.']);

    Activity::query()->update(['created_at' => Carbon::parse('2026-07-01 09:00:00')]);

    $this->actingAs($this->admin)
        ->get(route('admin.activity.index', [
            'range' => 'custom',
            'from' => '2026-07-01',
            'to' => '2026-07-01',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries', 2));
});

test('the audit trail refuses an unknown preset key', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.activity.index', ['range' => 'forever']))
        ->assertSessionHasErrors('range');
});

test('a preset narrows the orders table', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    Order::factory()->create(['placed_at' => Carbon::parse('2026-09-08 09:00:00')]);
    Order::factory()->create(['placed_at' => Carbon::parse('2026-06-08 09:00:00')]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['range' => 'last_7_days']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('orders', 1)
                ->where('filters.range', 'last_7_days')
                ->where('dateRange.start', '2026-09-03')
        );
});

test('choosing a range costs the dashboard no extra queries', function () {
    Carbon::setTestNow('2026-09-09 14:30:00');

    Order::factory()->count(3)->paid()->create(['paid_at' => Carbon::parse('2026-09-08 09:00:00')]);

    $count = function (array $query): int {
        $queries = 0;
        $recording = true;

        DB::listen(function () use (&$queries, &$recording): void {
            if ($recording) {
                $queries++;
            }
        });

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', $query))
            ->assertOk();

        $recording = false;

        return $queries;
    };

    // Warmed first. The very first request of a test also loads the permission
    // cache and the settings tables, and budgeting that against a later request
    // would measure the warm-up rather than the range.
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

    $default = $count([]);
    $narrowed = $count(['range' => 'last_7_days']);

    // The window changes the BOUNDS of the aggregates, never their number. A
    // panel that started resolving its own window would show up here as one
    // extra query per panel.
    expect($narrowed)->toBe($default);
});

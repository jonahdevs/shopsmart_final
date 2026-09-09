<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Visitor;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/**
 * The three panels added to the dashboard below the charts: recent activity,
 * visitors by platform and visitors by country.
 *
 * The visitor panels are about arithmetic — the shares are computed on the
 * server and the browser only draws widths and fills, so a wrong share is a
 * wrong panel. The activity panel is about who may see what: `activity.view` is
 * the gate on the panel existing at all, and the values inside it answer to the
 * permission that governs each subject.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('the visitor panels are deferred so the tiles paint first', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('visitors')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('visitors.platforms')
                ->has('visitors.countries')));
});

test('visitors are split by platform with server-computed shares', function () {
    Visitor::factory()->count(6)->create(['platform' => 'Windows']);
    Visitor::factory()->count(3)->create(['platform' => 'Android']);
    Visitor::factory()->create(['platform' => 'iPhone']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('visitors.sessionCount', 10)
                // Largest first, and the share is a percentage of the ten
                // sessions rather than a width the browser divided out.
                ->where('visitors.platforms.0.label', 'Windows')
                ->where('visitors.platforms.0.value', 6)
                ->where('visitors.platforms.0.share', 60)
                ->where('visitors.platforms.1.label', 'Android')
                ->where('visitors.platforms.1.share', 30)
                ->where('visitors.platforms.2.label', 'iPhone')
                ->where('visitors.platforms.2.share', 10)));
});

test('a platform nobody could read off the user agent is shown as unknown', function () {
    Visitor::factory()->count(2)->create(['platform' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('visitors.platforms.0.label', 'Unknown')
                ->where('visitors.platforms.0.share', 100)));
});

test('the platform tail is folded into one Other segment so the shares still total a hundred', function () {
    // Seven distinct platforms against a ramp of six colours. The sixth row
    // must be the remainder, not the sixth platform with the seventh dropped.
    foreach (['Windows', 'Android', 'iPhone', 'macOS', 'Linux'] as $index => $platform) {
        Visitor::factory()->count(10 - $index)->create(['platform' => $platform]);
    }

    Visitor::factory()->count(2)->create(['platform' => 'iPad']);
    Visitor::factory()->create(['platform' => 'Chrome OS']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('visitors.platforms', 6)
                ->where('visitors.platforms.5.label', 'Other')
                ->where('visitors.platforms.5.value', 3)
                ->where('visitors.sessionCount', 43)));
});

test('visitors are grouped by country, largest first, with a name for the map to label', function () {
    Visitor::factory()->count(6)->from('KE')->create();
    Visitor::factory()->count(3)->from('UG')->create();
    Visitor::factory()->from('GB')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('visitors.countries', 3)
                // The code is what the map addresses a region by; the label is
                // what a human reads. The server sends both.
                ->where('visitors.countries.0.code', 'KE')
                ->where('visitors.countries.0.label', 'Kenya')
                ->where('visitors.countries.0.count', 6)
                ->where('visitors.countries.0.formatted', '6')
                ->where('visitors.countries.0.share', 60)
                ->where('visitors.countries.1.code', 'UG')
                ->where('visitors.countries.1.share', 30)
                ->where('visitors.countries.2.code', 'GB')
                ->where('visitors.countries.2.share', 10)));
});

test('country shares total a hundred across the sessions that could be placed', function () {
    Visitor::factory()->count(3)->from('KE')->create();
    Visitor::factory()->count(2)->from('TZ')->create();
    Visitor::factory()->count(5)->unplaced()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // The unplaced half of the traffic is still a visit — the
                // platform panel counts all ten — but it is not a place, so the
                // map divides by the five it could locate rather than leaving
                // its shares mysteriously short of a hundred.
                ->where('visitors.sessionCount', 10)
                ->has('visitors.countries', 2)
                ->where('visitors.countries.0.code', 'KE')
                ->where('visitors.countries.0.share', 60)
                ->where('visitors.countries.1.code', 'TZ')
                ->where('visitors.countries.1.share', 40)));
});

test('a session the edge could not place is left off the map rather than counted as a blank country', function () {
    Visitor::factory()->count(4)->unplaced()->create();
    Visitor::factory()->from('KE')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('visitors.countries', 1)
                ->where('visitors.countries.0.code', 'KE')
                ->where('visitors.countries.0.share', 100)));
});

test('new and returning sessions are counted from the same window', function () {
    Visitor::factory()->count(4)->newVisitor()->create();
    Visitor::factory()->count(6)->returning()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('visitors.newCount', 4)
                ->where('visitors.returningCount', 6)));
});

test('sessions outside the window are not counted', function () {
    Visitor::factory()->create(['created_at' => now()->subDays(2)]);
    Visitor::factory()->create(['created_at' => now()->subDays(45)]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('visitors.sessionCount', 1)));
});

test('a store with no visitors sends empty breakdowns rather than nothing', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('visitors.sessionCount', 0)
                ->has('visitors.platforms', 0)
                // No country either, which is what puts the map panel into its
                // empty state instead of leaving it an unpainted world.
                ->has('visitors.countries', 0)));
});

test('the recent activity panel lists the newest entries first', function () {
    activity()->log('Older thing');
    activity()->log('Newer thing');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('activity.0.description', 'Newer thing')
                ->where('activity.1.description', 'Older thing')));
});

test('a staff member without activity.view gets no activity panel at all', function () {
    // A Manager runs day-to-day trading and cannot read the audit trail. The
    // rows are not hidden client-side — they are never sent.
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    activity()->log('Something happened');

    $this->actingAs($manager)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('visitors')
                ->missing('activity')));
});

test('the activity panel redacts the values of a subject the viewer may not read', function () {
    // Someone who may read the trail but not the orders in it. `activity.view`
    // buys the shape of an event, never automatically its contents.
    $role = Role::findOrCreate('Auditor', PermissionSeeder::GUARD);
    $role->givePermissionTo('activity.view');

    $auditor = User::factory()->create();
    $auditor->assignRole($role);

    $order = Order::factory()->create(['status' => OrderStatus::Processing]);
    $order->update(['status' => OrderStatus::Completed]);

    $this->actingAs($auditor)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('activity.0.valuesHidden', true)
                // The attribute name still shows — an auditor needs to know
                // something moved — but the values come through null.
                ->where('activity.0.changes.0.to', null)));
});

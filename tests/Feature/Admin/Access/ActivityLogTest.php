<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * The audit trail.
 *
 * Two properties matter more than the filters. It must be impossible to write
 * to — a log staff can edit is not evidence — and it must not become a side
 * channel around the permissions everywhere else: `activity.view` says you may
 * see that an order moved, not that you may read the order.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Admin');
});

/** A staff member holding a purpose-built role with only these permissions. */
function auditorWith(string ...$permissions): User
{
    $role = Role::create([
        'name' => 'Audit '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('the trail lists what the models recorded, newest first', function () {
    $earlier = Order::factory()->create();
    $earlier->update(['status' => OrderStatus::Processing]);

    $later = Order::factory()->create();
    $later->update(['staff_note' => 'Called the customer.']);

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/activity/Index')
                ->where('entries.0.logName', 'order')
                ->where('entries.0.event', 'updated')
                ->where('entries.0.subjectType', 'Order')
                ->where('entries.0.subjectLabel', $later->order_number)
                ->etc()
        );
});

test('a change is rendered as an attribute moving from one value to another', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    $order->update(['status' => OrderStatus::Processing]);

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index'))
        ->assertInertia(function ($page) {
            $changes = collect($page->toArray()['props']['entries'][0]['changes'])->keyBy('attribute');

            expect($changes['status']['from'])->toBe(OrderStatus::Pending->value)
                ->and($changes['status']['to'])->toBe(OrderStatus::Processing->value);
        });
});

test('the values are hidden from a viewer who may not read the subject', function () {
    // The trail records staff actions against customer records, so it is
    // personal data in its own right. The auditor may see that an order moved
    // without being allowed to read the order.
    $order = Order::factory()->create();
    $order->update(['status' => OrderStatus::Processing]);

    $blind = auditorWith('activity.view');

    $this->actingAs($blind)
        ->get(route('admin.activity.index'))
        ->assertInertia(function ($page) {
            $entry = $page->toArray()['props']['entries'][0];

            expect($entry['valuesHidden'])->toBeTrue()
                ->and($entry['changes'][0]['attribute'])->toBe('status')
                ->and($entry['changes'][0]['from'])->toBeNull()
                ->and($entry['changes'][0]['to'])->toBeNull();
        });

    $sighted = auditorWith('activity.view', 'orders.view');

    $this->actingAs($sighted)
        ->get(route('admin.activity.index'))
        ->assertInertia(
            fn ($page) => $page->where('entries.0.valuesHidden', false)->etc()
        );
});

test('the free-form properties bag is never sent to the page', function () {
    // `attribute_changes` is what a model chose to log. `properties` is a bag
    // any call site can put anything in, and a screen that dumped it would leak
    // whatever that turns out to be.
    $order = Order::factory()->create();

    activity('order')
        ->performedOn($order)
        ->withProperties(['customer_email' => 'shopper@example.test'])
        ->log('poked');

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index'))
        ->assertDontSee('shopper@example.test');
});

test('the trail filters by log, event, subject, who and date', function () {
    $order = Order::factory()->create();
    $order->update(['status' => OrderStatus::Processing]);

    $payment = Payment::factory()->create();
    $payment->update(['channel' => 'card']);

    $mover = User::factory()->create();
    activity('order')->performedOn($order)->causedBy($mover)->event('updated')->log('moved');

    // Counted by what came back rather than by a fixed number: creating a model
    // logs an entry of its own, so the filter is what is being asserted here,
    // not the fixture's arithmetic.
    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index', ['log_name' => 'payment']))
        ->assertInertia(function ($page) {
            $entries = collect($page->toArray()['props']['entries']);

            expect($entries)->not->toBeEmpty()
                ->and($entries->pluck('logName')->unique()->all())->toBe(['payment']);
        });

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index', ['subject_type' => Order::class]))
        ->assertInertia(function ($page) {
            $entries = collect($page->toArray()['props']['entries']);

            expect($entries)->not->toBeEmpty()
                ->and($entries->pluck('subjectType')->unique()->all())->toBe(['Order']);
        });

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index', ['causer_id' => $mover->getKey()]))
        ->assertInertia(fn ($page) => $page->has('entries', 1)->where('entries.0.causerName', $mover->name)->etc());

    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index', ['from' => now()->addDay()->toDateString()]))
        ->assertInertia(fn ($page) => $page->has('entries', 0)->etc());
});

test('a sort column outside the whitelist is rejected', function () {
    $this->actingAs($this->auditor)
        ->get(route('admin.activity.index', ['sort' => 'properties']))
        ->assertSessionHasErrors('sort');
});

test('there is no way to write to the activity log', function () {
    Activity::query()->delete();

    $order = Order::factory()->create();
    $order->update(['status' => OrderStatus::Processing]);

    $before = Activity::query()->count();

    foreach (['post', 'patch', 'put', 'delete'] as $method) {
        $this->actingAs($this->auditor)
            ->{$method}(route('admin.activity.index'))
            ->assertMethodNotAllowed();
    }

    expect(Activity::query()->count())->toBe($before);
});

test('a staff member without activity.view is refused', function () {
    $support = User::factory()->create();
    $support->assignRole('Support');

    $this->actingAs($support)->get(route('admin.activity.index'))->assertForbidden();
});

test('a customer is refused and a guest is sent to log in', function () {
    $this->get(route('admin.activity.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.activity.index'))
        ->assertForbidden();
});

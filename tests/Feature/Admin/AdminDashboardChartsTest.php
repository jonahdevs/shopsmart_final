<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

/**
 * The dashboard's charts.
 *
 * These are separate from the tile arithmetic in AdminDashboardTest because
 * they answer a different question: the tiles are about which rows count, and
 * these are about the SHAPE of what is sent — contiguous buckets, statuses that
 * appear even at zero, and caps that keep a chart readable.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('the charts are deferred so the tiles paint first', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('stats')
            ->has('recentOrders')
            ->missing('charts')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.timeline')
                ->has('charts.ordersByStatus')));
});

test('the timeline zero-fills days with no trading', function () {
    Order::factory()->paid()->create([
        'total_cents' => 150_000,
        'paid_at' => now()->subDays(3),
    ]);

    // The window is named rather than left to the default: this test is about
    // zero-filling, and it should not start failing the day somebody changes
    // which preset the dashboard opens on.
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'last_7_days']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // "Last 7 days" is seven contiguous buckets, today included. A
                // GROUP BY alone would have returned exactly one row, and a
                // line drawn through one row invents the other six.
                ->has('charts.timeline.labels', 7)
                ->has('charts.timeline.revenue', 7)
                ->has('charts.timeline.orders', 7)
                ->has('charts.timeline.customers', 7)
                // Every quiet day is a real zero, not a gap.
                ->where(
                    'charts.timeline.revenue',
                    fn (Collection $revenue) => (float) $revenue->sum() === 1500.0
                )));
});

test('the timeline reports revenue in major units so an axis can label it', function () {
    Order::factory()->paid()->create([
        'total_cents' => 250_000,
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // 250_000 cents is KES 2,500 — not 250000 on the axis.
                ->where(
                    'charts.timeline.revenue',
                    fn (Collection $revenue) => (float) $revenue->sum() === 2500.0
                )
                ->where('charts.timeline.currencySymbol', 'KES')));
});

test('every order status appears even when none are in it', function () {
    Order::factory()->create([
        'status' => OrderStatus::Pending,
        'placed_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // A status chart that silently omits "Cancelled" reads as a
                // store with no cancellations, so all six are always sent.
                ->has('charts.ordersByStatus', count(OrderStatus::cases()))
                ->where(
                    'charts.ordersByStatus',
                    fn (Collection $slices) => $slices->firstWhere(
                        'label',
                        OrderStatus::Cancelled->label()
                    )['value'] === 0
                )));
});

test('revenue by method labels the gateway in words a human reads', function () {
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'payment_method' => 'paystack',
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('charts.revenueByMethod.0.label', 'Card / M-Pesa')
                ->where('charts.revenueByMethod.0.formatted', 'KES 1,000')));
});

test('an unpaid order contributes nothing to revenue by method', function () {
    Order::factory()->create([
        'total_cents' => 900_000,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'paystack',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.revenueByMethod', 0)));
});

test('top products rank by units sold and ignore cancelled orders', function () {
    $sold = Order::factory()->paid()->create(['placed_at' => now()->subDay()]);
    $cancelled = Order::factory()->cancelled()->create(['placed_at' => now()->subDay()]);

    OrderItem::factory()->for($sold)->create(['name' => 'Jiko Stove', 'quantity' => 7]);
    OrderItem::factory()->for($sold)->create(['name' => 'Sufuria Set', 'quantity' => 3]);
    // Cancelled units were never shipped and must not rank.
    OrderItem::factory()->for($cancelled)->create(['name' => 'Thermos', 'quantity' => 99]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.topProducts', 2)
                ->where('charts.topProducts.0.label', 'Jiko Stove')
                ->where('charts.topProducts.0.value', 7)
                ->where('charts.topProducts.0.formatted', '7 units')
                ->where(
                    'charts.topProducts',
                    fn (Collection $top) => ! $top->pluck('label')->contains('Thermos')
                )));
});

test('top products is capped so the chart stays readable', function () {
    $order = Order::factory()->paid()->create(['placed_at' => now()->subDay()]);

    foreach (range(1, 9) as $index) {
        OrderItem::factory()->for($order)->create([
            'name' => "Product {$index}",
            'quantity' => $index,
        ]);
    }

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.topProducts', 6)));
});

test('a product in two categories counts its units in both aisles', function () {
    $kitchen = Category::factory()->create(['name' => 'Kitchen']);
    $outdoor = Category::factory()->create(['name' => 'Outdoor']);

    $product = Product::factory()->create();
    $product->categories()->sync([$kitchen->id, $outdoor->id]);

    $order = Order::factory()->paid()->create(['placed_at' => now()->subDay()]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'quantity' => 4,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // Deliberate double-counting: the question is "which aisles are
                // selling", so four units sold from a product filed in two
                // categories is four units of interest in each.
                ->where('charts.topCategories', function (Collection $aisles) {
                    $byName = $aisles->pluck('value', 'label');

                    return (float) $byName['Kitchen'] === 4.0
                        && (float) $byName['Outdoor'] === 4.0;
                })));
});

test('the rating spread counts approved reviews only and averages them', function () {
    $product = Product::factory()->create();

    Review::factory()->count(3)->for($product)->create([
        'rating' => 5,
        'status' => ReviewStatus::Approved,
    ]);
    Review::factory()->for($product)->create([
        'rating' => 3,
        'status' => ReviewStatus::Approved,
    ]);
    // Pending reviews are not yet the store's reputation.
    Review::factory()->for($product)->create([
        'rating' => 1,
        'status' => ReviewStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('charts.reviewCount', 4)
                // (5+5+5+3) / 4
                ->where('charts.averageRating', 4.5)
                // Five buckets, five stars down to one, always.
                ->has('charts.ratings', 5)
                ->where('charts.ratings.0.value', 3)));
});

test('a store with no approved reviews reports no average rather than zero', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('charts.averageRating', null)
                ->where('charts.reviewCount', 0)));
});

test('the timeline carries an average order value per day so the fourth tile has a sparkline', function () {
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'paid_at' => now()->subDay(),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 300_000,
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['range' => 'last_7_days']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.timeline.averageOrder', 7)
                // KES 2,000 on the one trading day and a real 0 on the other
                // six — a quiet day has no average order, and smearing the
                // window's mean across it would draw trading that never
                // happened.
                ->where(
                    'charts.timeline.averageOrder',
                    fn (Collection $averages) => (float) $averages->sum() === 2000.0
                        && $averages->filter()->count() === 1
                )));
});

test('each status carries its share of the window and the badge variant it is drawn with', function () {
    Order::factory()->count(3)->create([
        'status' => OrderStatus::Pending,
        'placed_at' => now()->subDay(),
    ]);
    Order::factory()->cancelled()->create(['placed_at' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('charts.ordersByStatus', function (Collection $slices) {
                    $pending = $slices->firstWhere('label', OrderStatus::Pending->label());
                    $cancelled = $slices->firstWhere('label', OrderStatus::Cancelled->label());
                    $completed = $slices->firstWhere('label', OrderStatus::Completed->label());

                    // The ladder has no axis, so the share is a figure the
                    // server owes it rather than a width the page divides out.
                    return (float) $pending['share'] === 75.0
                        && (float) $cancelled['share'] === 25.0
                        && (float) $completed['share'] === 0.0
                        // Tinted from the enum, so a status added in app/Enums
                        // arrives with its colour already decided.
                        && $pending['variant'] === OrderStatus::Pending->badgeVariant()
                        && $cancelled['variant'] === OrderStatus::Cancelled->badgeVariant();
                })));
});

test('revenue by method carries the total the donut prints in its hole', function () {
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'payment_method' => 'bank_transfer',
        'paid_at' => now()->subDay(),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 300_000,
        'payment_method' => 'paystack',
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // The sum of the slices, formatted by the server. The hole and
                // the rows around it have to be the same arithmetic, and the
                // browser never turns cents into a currency string.
                ->where('charts.revenueByMethodTotalFormatted', 'KES 4,000')));
});

test('a store with nothing captured still reports a total the panel can print', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('charts.revenueByMethod', 0)
                // Zero money, not an empty string or a null the page would
                // have to guard: the panel shows its empty state instead, but
                // the prop is still a money string either way.
                ->where('charts.revenueByMethodTotalFormatted', 'KES 0')));
});

test('revenue by method carries each share so the donut and its legend cannot disagree', function () {
    Order::factory()->paid()->create([
        'total_cents' => 100_000,
        'payment_method' => 'bank_transfer',
        'paid_at' => now()->subDay(),
    ]);
    Order::factory()->paid()->create([
        'total_cents' => 300_000,
        'payment_method' => 'paystack',
        'paid_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // Ordered by revenue, so the biggest method leads both the
                // donut and the list beneath it.
                ->where('charts.revenueByMethod.0.label', 'Card / M-Pesa')
                ->where('charts.revenueByMethod.0.share', 75)
                ->where('charts.revenueByMethod.1.share', 25)));
});

test('the rating spread carries each star its share of approved reviews', function () {
    $product = Product::factory()->create();

    Review::factory()->count(3)->for($product)->create([
        'rating' => 5,
        'status' => ReviewStatus::Approved,
    ]);
    Review::factory()->for($product)->create([
        'rating' => 3,
        'status' => ReviewStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // Five down to one, every row measured against the same total
                // rather than a running one.
                ->where('charts.ratings.0.share', 75)
                ->where('charts.ratings.2.share', 25)
                ->where('charts.ratings.4.share', 0)));
});

test('a breakdown with nothing in it reports a zero share rather than dividing by zero', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where(
                    'charts.ordersByStatus',
                    fn (Collection $slices) => $slices->every(
                        fn (array $slice) => (float) $slice['share'] === 0.0
                    )
                )));
});

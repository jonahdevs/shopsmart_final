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

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                // A 30-day window is 31 contiguous buckets, inclusive of both
                // ends. A GROUP BY alone would have returned exactly one row,
                // and a line drawn through one row invents the other thirty.
                ->has('charts.timeline.labels', 31)
                ->has('charts.timeline.revenue', 31)
                ->has('charts.timeline.orders', 31)
                ->has('charts.timeline.customers', 31)
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

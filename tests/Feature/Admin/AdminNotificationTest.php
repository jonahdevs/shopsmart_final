<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\Staff\NewOrderPlaced;
use App\Notifications\Staff\PaymentReceived;
use App\Services\Paystack\PaystackPaymentService;
use App\Settings\CheckoutSettings;
use App\Settings\PaymentApiSettings;
use App\Settings\PaymentSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Database\Seeders\PermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * The admin header's notification bell.
 *
 * Almost every test here is an access-control test rather than a UI one. A
 * notification is a claim about a record — an order's total, a payment's amount
 * — so who may read one is the same question as who may read the record, and
 * the bell is a second surface that has to answer it the same way the order
 * screen does.
 *
 * The other half is ownership. Marking something read is a write, and the only
 * rows a staff member may write are their own.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    // Manager holds orders.view and payments.view: both kinds reach this bell.
    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');

    // Orders but not payments — the viewer that proves the filter is per type
    // and not merely "is this person staff".
    $this->orderClerk = User::factory()->create();
    $this->orderClerk->assignRole(
        Role::create(['name' => 'Order Clerk', 'guard_name' => PermissionSeeder::GUARD])
            ->syncPermissions(['orders.view'])
    );

    // Staff, but with nothing to do with sales at all.
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole(
        Role::create(['name' => 'Stock Clerk', 'guard_name' => PermissionSeeder::GUARD])
            ->syncPermissions(['products.view'])
    );
});

/**
 * Ask the dashboard for the bell's optional `items` prop the way the panel does
 * when it opens.
 *
 * Two requests because a partial reload must carry the asset version the first
 * response was built with, and the middleware decides that per request.
 *
 * @return array<int, array<string, mixed>>
 */
function bellItemsFor(User $user): array
{
    $version = test()->actingAs($user)
        ->get(route('admin.dashboard'))
        ->viewData('page')['version'];

    return test()->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'admin/Dashboard',
            'X-Inertia-Partial-Data' => 'notifications.items',
        ])
        ->get(route('admin.dashboard'))
        ->json('props.notifications.items') ?? [];
}

test('the bell only carries notifications the viewer holds the permission for', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id]);

    $this->orderClerk->notify(new NewOrderPlaced($order));
    $this->orderClerk->notify(new PaymentReceived($payment));

    $items = bellItemsFor($this->orderClerk);

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toContain($order->order_number)
        ->and($items[0]['icon'])->toBe('order');

    // The row is still in the table — it is the READ that is filtered, so the
    // rows come back if the permission is ever granted.
    expect(DatabaseNotification::query()->count())->toBe(2);
});

test('the bell is empty for a staff member with no sales permission at all', function () {
    $this->stockClerk->notify(new NewOrderPlaced(Order::factory()->create()));

    expect(bellItemsFor($this->stockClerk))->toBe([]);
});

test('the unread count counts only unread notifications the viewer may see', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id]);

    $this->orderClerk->notify(new NewOrderPlaced($order));
    $this->orderClerk->notify(new NewOrderPlaced(Order::factory()->create()));
    $this->orderClerk->notify(new PaymentReceived($payment));
    $this->orderClerk->notify(new NewOrderPlaced(Order::factory()->create()));

    $this->orderClerk->notifications()
        ->where('type', NewOrderPlaced::class)
        ->firstOrFail()
        ->markAsRead();

    // Four rows: one is a payment this viewer may not see and one of the three
    // orders has been read, so the badge says two.
    $this->actingAs($this->orderClerk)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('notifications.unreadCount', 2));
});

test('the storefront pays nothing for a bell it does not render', function () {
    $this->manager->notify(new NewOrderPlaced(Order::factory()->create()));

    $this->actingAs($this->manager)
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('notifications.unreadCount', 0));
});

test('opening a notification marks exactly that one read and goes to the record', function () {
    $order = Order::factory()->create();

    $this->manager->notify(new NewOrderPlaced($order));
    $this->manager->notify(new NewOrderPlaced(Order::factory()->create()));

    $opened = $this->manager->notifications()->oldest()->firstOrFail();

    $this->actingAs($this->manager)
        ->get(route('admin.notifications.show', $opened->getKey()))
        ->assertRedirect(route('admin.orders.show', $order->order_number));

    expect($opened->fresh()?->read_at)->not->toBeNull()
        ->and($this->manager->unreadNotifications()->count())->toBe(1);
});

test('a notification cannot be opened by a staff member who does not own it', function () {
    $this->manager->notify(new NewOrderPlaced(Order::factory()->create()));

    $someoneElses = $this->manager->notifications()->firstOrFail();

    $this->actingAs($this->orderClerk)
        ->get(route('admin.notifications.show', $someoneElses->getKey()))
        ->assertNotFound();

    expect($someoneElses->fresh()?->read_at)->toBeNull();
});

test('owning a notification is not enough to open one the permission does not cover', function () {
    $payment = Payment::factory()->create(['order_id' => Order::factory()->create()->id]);

    $this->orderClerk->notify(new PaymentReceived($payment));

    $notification = $this->orderClerk->notifications()->firstOrFail();

    $this->actingAs($this->orderClerk)
        ->get(route('admin.notifications.show', $notification->getKey()))
        ->assertForbidden();

    expect($notification->fresh()?->read_at)->toBeNull();
});

test('mark all read clears this viewer and leaves everyone else alone', function () {
    $order = Order::factory()->create();

    $this->manager->notify(new NewOrderPlaced($order));
    $this->manager->notify(new NewOrderPlaced(Order::factory()->create()));
    $this->orderClerk->notify(new NewOrderPlaced($order));

    $this->actingAs($this->manager)
        ->from(route('admin.dashboard'))
        ->post(route('admin.notifications.read-all'))
        ->assertRedirect(route('admin.dashboard'));

    expect($this->manager->unreadNotifications()->count())->toBe(0)
        ->and($this->orderClerk->unreadNotifications()->count())->toBe(1);
});

test('mark all read leaves rows the viewer was never allowed to see unread', function () {
    $payment = Payment::factory()->create(['order_id' => Order::factory()->create()->id]);

    $this->orderClerk->notify(new NewOrderPlaced(Order::factory()->create()));
    $this->orderClerk->notify(new PaymentReceived($payment));

    $this->actingAs($this->orderClerk)
        ->from(route('admin.dashboard'))
        ->post(route('admin.notifications.read-all'));

    // The payment row is untouched: the clerk cannot have dealt with something
    // the bell never showed them, and it should still be waiting if
    // `payments.view` is granted later.
    expect($this->orderClerk->unreadNotifications()->count())->toBe(1)
        ->and($this->orderClerk->unreadNotifications()->sole()->type)
        ->toBe(PaymentReceived::class);
});

test('a guest is refused the bell entirely', function () {
    $this->manager->notify(new NewOrderPlaced(Order::factory()->create()));

    $notification = $this->manager->notifications()->firstOrFail();

    $this->get(route('admin.notifications.show', $notification->getKey()))
        ->assertRedirect(route('login'));

    $this->post(route('admin.notifications.read-all'))
        ->assertRedirect(route('login'));
});

describe('what checkout writes', function () {
    beforeEach(function () {
        $tax = app(TaxSettings::class);
        $tax->tax_enabled = false;
        $tax->save();

        $shipping = app(ShippingSettings::class);
        $shipping->flat_rate_cents = 0;
        $shipping->free_shipping_threshold_cents = 0;
        $shipping->save();

        $checkout = app(CheckoutSettings::class);
        $checkout->min_order_value_cents = 0;
        $checkout->order_prefix = 'SS-';
        $checkout->save();

        $payments = app(PaymentSettings::class);
        $payments->cash_on_delivery_enabled = true;
        $payments->save();

        $this->customer = User::factory()->create();
    });

    test('placing an order tells the staff who may see orders, and nobody else', function () {
        Notification::fake();

        $product = Product::factory()->published()->create([
            'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
        ]);

        $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'cash_on_delivery',
            'quoted_total_cents' => 150_000,
        ])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($this->manager, NewOrderPlaced::class, 1);
        Notification::assertSentToTimes($this->orderClerk, NewOrderPlaced::class, 1);
        Notification::assertNotSentTo($this->stockClerk, NewOrderPlaced::class);

        // And the customer gets their own message, on their own channel.
        Notification::assertSentToTimes($this->customer, OrderPlaced::class, 1);
        Notification::assertNotSentTo($this->customer, NewOrderPlaced::class);
    });

    test('settling a payment tells the staff who may see payments, exactly once', function () {
        Notification::fake();
        Http::preventStrayRequests();

        $paystack = app(PaymentSettings::class);
        $paystack->paystack_enabled = true;
        $paystack->save();

        $api = app(PaymentApiSettings::class);
        $api->paystack_secret_key = 'sk_test_bell';
        $api->save();

        $order = Order::factory()->create(['total_cents' => 530_000, 'currency' => 'KES']);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'reference' => $order->order_number.'-a1b2c3d4',
            'amount_cents' => 530_000,
            'currency' => 'KES',
        ]);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'id' => 3_004_567,
                'status' => 'success',
                'amount' => 530_000,
                'currency' => 'KES',
                'channel' => 'mobile_money',
                'gateway_response' => 'Successful',
            ],
        ])]);

        $service = app(PaystackPaymentService::class);

        $service->verify($payment->reference);
        // The webhook, arriving after the browser already verified. The payment
        // is final by now, so this must not ring a second time.
        $service->verify($payment->reference);

        Notification::assertSentToTimes($this->manager, PaymentReceived::class, 1);
        Notification::assertNotSentTo($this->orderClerk, PaymentReceived::class);
        Notification::assertNotSentTo($this->stockClerk, PaymentReceived::class);
    });

    test("a customer's own notification never lands in the table the bell reads", function () {
        $product = Product::factory()->published()->create([
            'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
        ]);

        $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method' => 'pickup',
            'payment_method' => 'cash_on_delivery',
            'quoted_total_cents' => 150_000,
        ])->assertSessionHasNoErrors();

        // OrderPlaced is `via: ['mail']`, so the customer owns no row at all —
        // the bell's filter is never the only thing standing between a
        // shopper's message and a staff member's inbox.
        expect(DatabaseNotification::query()
            ->where('notifiable_id', $this->customer->getKey())
            ->count())->toBe(0);

        expect(bellItemsFor($this->manager))->toHaveCount(1);
    });
});

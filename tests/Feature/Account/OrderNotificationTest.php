<?php

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Notifications\OrderPaid;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Settings\CheckoutSettings;
use App\Settings\PaymentSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Illuminate\Support\Facades\Notification;

/**
 * The three transactional emails, and the guards that stop a shopper getting
 * the same one twice.
 *
 * The one that matters most is OrderPaid: a payment can confirm twice, once
 * from the browser's verify call and once from the gateway's webhook, and only
 * the branch of markPaid() whose conditional UPDATE actually moved the row may
 * send a receipt.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    Notification::fake();

    $this->standardVat = TaxClass::factory()->standardVat()->create();

    $tax = app(TaxSettings::class);
    $tax->tax_enabled = true;
    $tax->prices_include_tax = true;
    $tax->default_tax_class_id = $this->standardVat->id;
    $tax->save();

    $shipping = app(ShippingSettings::class);
    $shipping->flat_rate_cents = 30_000;
    $shipping->free_shipping_threshold_cents = 5_000_000;
    $shipping->save();

    $checkout = app(CheckoutSettings::class);
    $checkout->min_order_value_cents = 0;
    $checkout->order_prefix = 'SS-';
    $checkout->save();

    // An offline method: what is under test is the email, not collecting.
    $payments = app(PaymentSettings::class);
    $payments->cash_on_delivery_enabled = true;
    $payments->save();

    $this->customer = User::factory()->create();
    $this->address = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);
});

test('placing an order emails the customer exactly once', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 180_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    Notification::assertSentToTimes($this->customer, OrderPlaced::class, 1);
    Notification::assertSentTo(
        $this->customer,
        OrderPlaced::class,
        fn (OrderPlaced $notification): bool => $notification->order->is($order),
    );
});

test('settling an order emails the receipt once', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeTrue();

    Notification::assertSentToTimes($this->customer, OrderPaid::class, 1);
});

test('a replayed payment confirmation sends no second receipt', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeTrue()
        ->and($order->fresh()->markPaid('paystack'))->toBeFalse();

    Notification::assertSentToTimes($this->customer, OrderPaid::class, 1);
});

test('an order that cannot be settled sends nothing at all', function () {
    $order = Order::factory()->cancelled()->create(['user_id' => $this->customer->id]);

    expect($order->markPaid('paystack'))->toBeFalse();

    Notification::assertNothingSentTo($this->customer);
});

test('a fulfilment transition tells the customer what changed', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);

    expect($order->changeStatus(OrderStatus::OutForDelivery))->toBeTrue();

    Notification::assertSentTo(
        $this->customer,
        OrderStatusChanged::class,
        fn (OrderStatusChanged $notification): bool => $notification->previousStatus === OrderStatus::Processing
            && $notification->order->status === OrderStatus::OutForDelivery,
    );

    expect($order->fresh()->status)->toBe(OrderStatus::OutForDelivery);
});

test('re-saving the status the order already has sends nothing', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);

    expect($order->changeStatus(OrderStatus::Processing))->toBeFalse();

    Notification::assertNothingSentTo($this->customer);
});

test('cancelling through a status change stamps the moment', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    expect($order->changeStatus(OrderStatus::Cancelled))->toBeTrue()
        ->and($order->fresh()->cancelled_at)->not->toBeNull();
});

test('an order whose customer deleted their account emails nobody', function () {
    $order = Order::factory()->create(['user_id' => null]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeTrue();

    Notification::assertNothingSent();
});

<?php

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\PaymentSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;

/**
 * Placing an order: what gets written, what gets frozen onto the row, and what
 * deliberately does NOT happen yet.
 *
 * An order comes out pending. No stock moves, no coupon is redeemed and no
 * payment row is written until a gateway is actually asked for the money, so an
 * abandoned checkout cannot eat a limited coupon's budget or hold stock nobody
 * paid for.
 */
beforeEach(function () {
    $this->standardVat = TaxClass::factory()->standardVat()->create();

    $tax = app(TaxSettings::class);
    $tax->tax_enabled = true;
    $tax->prices_include_tax = true;
    $tax->default_tax_class_id = $this->standardVat->id;
    $tax->save();

    $shipping = app(ShippingSettings::class);
    $shipping->flat_rate_cents = 30_000;
    $shipping->free_shipping_threshold_cents = 5_000_000;
    $shipping->local_pickup_enabled = true;
    $shipping->save();

    $checkout = app(CheckoutSettings::class);
    $checkout->min_order_value_cents = 0;
    $checkout->order_prefix = 'SS-';
    $checkout->save();

    // An offline method: placement is what is under test here, not collecting.
    $payments = app(PaymentSettings::class);
    $payments->cash_on_delivery_enabled = true;
    $payments->save();

    $this->customer = User::factory()->create();
    $this->address = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);
});

test('a delivery order writes one order and its lines, and no payment', function () {
    $first = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);
    $second = Product::factory()->published()->create([
        'price' => 200_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $first->id, 'quantity' => 2]);
    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $second->id, 'quantity' => 1]);

    // 300,000 + 200,000 goods, plus the 30,000 flat delivery rate.
    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 530_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect(Order::count())->toBe(1)
        ->and($order->items()->count())->toBe(2)
        ->and($order->payments()->count())->toBe(0)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payment_method)->toBe('cash_on_delivery')
        ->and($order->subtotal_cents)->toBe(500_000)
        ->and($order->shipping_cents)->toBe(30_000)
        ->and($order->total_cents)->toBe(530_000)
        ->and($order->user_id)->toBe($this->customer->id)
        ->and($order->customer_name)->toBe($this->customer->name)
        ->and($order->customer_email)->toBe($this->customer->email);

    // A payment row means "an attempt to collect", and it carries the reference
    // the gateway is handed. Nothing has been attempted yet, and writing one now
    // would mean either rewriting its reference later or leaving a settled
    // transaction with no row to match.
    $this->assertDatabaseCount('payments', 0);
});

test('the shopper lands on the order that was just placed', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 180_000,
    ])->assertRedirect(route('orders.show', Order::query()->sole()->order_number));
});

test('the order number carries the store prefix and a six digit counter', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 180_000,
    ]);

    expect(Order::query()->sole()->order_number)->toMatch('/^SS-\d{6}$/');
});

test('two orders get consecutive distinct numbers', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 50,
    ]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method' => 'delivery',
            'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery',
            'quoted_total_cents' => 180_000,
        ])->assertSessionHasNoErrors();
    }

    $numbers = Order::query()->orderBy('id')->pluck('order_number')->all();

    expect($numbers)->toBe(['SS-000001', 'SS-000002']);
});

test('the cart and the session coupon are emptied by placing the order', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create([
        'price' => 300_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);
    $this->actingAs($this->customer)->post(route('checkout.coupon.store'), ['code' => $coupon->code]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 280_000,
    ])->assertSessionHasNoErrors();

    expect(session('storefront.cart', []))->toBe([])
        ->and(session('storefront.coupon'))->toBeNull();

    $this->assertDatabaseCount('cart_items', 0);
});

test('the destination is copied onto the order and survives deleting the address', function () {
    $address = Address::factory()->create([
        'user_id' => $this->customer->id,
        'first_name' => 'Amina',
        'last_name' => 'Wanjiru',
        'phone' => '0712345678',
        'line1' => '14 Ngong Road',
        'city' => 'Nairobi',
        'country_code' => 'KE',
    ]);

    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 180_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->shipping_first_name)->toBe('Amina')
        ->and($order->shipping_last_name)->toBe('Wanjiru')
        ->and($order->shipping_phone)->toBe('0712345678')
        ->and($order->shipping_line1)->toBe('14 Ngong Road')
        ->and($order->shipping_city)->toBe('Nairobi')
        ->and($order->shipping_country_code)->toBe('KE')
        ->and($order->customer_phone)->toBe('0712345678');

    $address->delete();

    $order->refresh();

    // The link goes; what was actually shipped to does not.
    expect($order->shipping_address_id)->toBeNull()
        ->and($order->shipping_first_name)->toBe('Amina')
        ->and($order->shipping_line1)->toBe('14 Ngong Road')
        ->and($order->shipping_city)->toBe('Nairobi')
        ->and($order->shipping_country_code)->toBe('KE');
});

test('a collection order carries no destination and no delivery charge', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'pickup',
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 150_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->delivery_method)->toBe(DeliveryMethod::Pickup)
        ->and($order->shipping_cents)->toBe(0)
        ->and($order->total_cents)->toBe(150_000)
        ->and($order->shipping_address_id)->toBeNull()
        ->and($order->shipping_first_name)->toBeNull()
        ->and($order->shipping_last_name)->toBeNull()
        ->and($order->shipping_phone)->toBeNull()
        ->and($order->shipping_line1)->toBeNull()
        ->and($order->shipping_city)->toBeNull()
        ->and($order->shipping_country_code)->toBeNull();
});

test('placing an order moves no stock and redeems no coupon', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create([
        'price' => 300_000, 'sale_price' => null, 'stock_quantity' => 7,
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);
    $this->actingAs($this->customer)->post(route('checkout.coupon.store'), ['code' => $coupon->code]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 880_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($product->fresh()->stock_quantity)->toBe(7)
        ->and($order->stock_deducted_at)->toBeNull()
        ->and($coupon->fresh()->used_count)->toBe(0)
        ->and($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe($coupon->code);

    $this->assertDatabaseCount('coupon_uses', 0);
});

test('the order lines add up to the order total', function () {
    $coupon = Coupon::factory()->fixed(10)->create();

    $first = Product::factory()->published()->create(['price' => 33_333, 'sale_price' => null, 'stock_quantity' => 10]);
    $second = Product::factory()->published()->create(['price' => 66_667, 'sale_price' => null, 'stock_quantity' => 10]);
    $third = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null, 'stock_quantity' => 10]);

    foreach ([$first, $second, $third] as $product) {
        $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);
    }

    $this->actingAs($this->customer)->post(route('checkout.coupon.store'), ['code' => $coupon->code]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'payment_method' => 'cash_on_delivery',
        'quoted_total_cents' => 229_990,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    $lines = $order->items;

    // A line total is what that line contributes; delivery is the only figure
    // on the order that no line carries.
    expect($lines->sum('total_cents') + $order->shipping_cents)->toBe($order->total_cents)
        ->and($lines->sum('subtotal_cents'))->toBe($order->subtotal_cents)
        ->and($lines->sum('discount_cents'))->toBe($order->discount_cents);
});

test('deleting the customer leaves the order and its snapshot standing', function () {
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
    $name = $this->customer->name;
    $email = $this->customer->email;

    $this->customer->delete();

    $order->refresh();

    expect(Order::count())->toBe(1)
        ->and($order->user_id)->toBeNull()
        ->and($order->customer_name)->toBe($name)
        ->and($order->customer_email)->toBe($email)
        ->and($order->items()->count())->toBe(1);
});

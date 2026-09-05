<?php

use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Inertia\Testing\AssertableInertia;

/**
 * The confirmation page and the order history behind it.
 *
 * The page is built entirely from the order's own columns and its frozen lines,
 * never from the catalog, so a product renamed or repriced afterwards cannot
 * change what a shopper's receipt says they bought.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 4 page components (shop/Order, shop/Orders) are not written
    // yet. What is under test here is the prop contract the controller
    // publishes, not the existence of a Vue module.
    config()->set('inertia.testing.ensure_pages_exist', false);

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

    $this->customer = User::factory()->create();
    $this->address = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);
});

test('the confirmation shows the order that was just placed', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10, 'name' => 'Ridgeline Drill',
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'quoted_total_cents' => 330_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    $this->actingAs($this->customer)
        ->get(route('orders.show', $order->order_number))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Order')
            ->where('order.orderNumber', $order->order_number)
            ->where('order.customerEmail', $this->customer->email)
            ->where('order.paymentStatus', PaymentStatus::Pending->value)
            ->where('order.awaitsPayment', true)
            ->has('order.lines', 1)
            ->where('order.lines.0.name', 'Ridgeline Drill')
            ->where('order.lines.0.quantity', 2)
            ->where('order.itemCount', 2)
            ->where('order.totals.totalCents', 330_000)
            ->where('order.shippingAddress.line1', $this->address->line1)
            ->has('breadcrumbs', 2));
});

test('the order page reads the snapshot, not the catalog', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000, 'sale_price' => null, 'stock_quantity' => 10, 'name' => 'Ridgeline Drill',
    ]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'delivery_method' => 'delivery',
        'address_id' => $this->address->id,
        'quoted_total_cents' => 180_000,
    ])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    $product->update(['name' => 'Ridgeline Drill Mk II', 'price' => 999_000]);

    $this->actingAs($this->customer)
        ->get(route('orders.show', $order->order_number))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.lines.0.name', 'Ridgeline Drill')
            ->where('order.lines.0.unitPriceCents', 150_000)
            ->where('order.totals.totalCents', 180_000));
});

test('another shopper order is not found rather than forbidden', function () {
    $stranger = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $stranger->id]);

    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs($this->customer)
        ->get(route('orders.show', $order->order_number))
        ->assertNotFound();
});

test('the history lists only this shopper orders, newest first', function () {
    $stranger = User::factory()->create();

    $oldest = Order::factory()->create([
        'user_id' => $this->customer->id,
        'placed_at' => now()->subDays(3),
    ]);
    $newest = Order::factory()->create([
        'user_id' => $this->customer->id,
        'placed_at' => now()->subHour(),
    ]);
    $middle = Order::factory()->create([
        'user_id' => $this->customer->id,
        'placed_at' => now()->subDay(),
    ]);

    Order::factory()->create(['user_id' => $stranger->id, 'placed_at' => now()]);

    $this->actingAs($this->customer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Orders')
            ->has('orders', 3)
            ->where('orders.0.orderNumber', $newest->order_number)
            ->where('orders.1.orderNumber', $middle->order_number)
            ->where('orders.2.orderNumber', $oldest->order_number)
            ->where('hasMore', false)
            ->has('breadcrumbs', 2));
});

test('a shopper with no orders gets an empty history', function () {
    $this->actingAs($this->customer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('orders', 0));
});

test('a guest cannot read an order', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    $this->get(route('orders.show', $order->order_number))->assertRedirect(route('login'));
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

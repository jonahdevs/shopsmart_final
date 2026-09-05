<?php

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Who is allowed onto the checkout page, and what it hands the client.
 *
 * The three refusals are deliberately different shapes — a guest is sent to
 * sign in, an unverified account to the verification notice, and a staff
 * account back to the cart with an explanation rather than a 403.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 4 page components (shop/Checkout, shop/Order, shop/Orders) are
    // not written yet. What is under test here is the prop contract the
    // controller publishes, not the existence of a Vue module.
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
});

test('a guest is sent to sign in', function () {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
});

test('an unverified account is sent to the verification notice', function () {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)
        ->get(route('checkout.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a staff account is sent back to the cart rather than refused outright', function () {
    $this->seed(PermissionSeeder::class);

    $staff = User::factory()->create();
    $staff->assignRole('Support');

    $this->actingAs($staff)
        ->get(route('checkout.index'))
        ->assertRedirect(route('cart.index'));
});

test('an empty cart is sent back to the cart page', function () {
    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertRedirect(route('cart.index'));
});

test('the checkout renders the priced cart', function () {
    $product = Product::factory()->published()->create([
        'price' => 150_000,
        'sale_price' => null,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Checkout')
            ->has('quote.lines', 1)
            ->where('quote.lines.0.productId', $product->id)
            ->where('quote.lines.0.quantity', 2)
            ->where('quote.totals.subtotalCents', 300_000)
            ->where('quote.totals.shippingCents', 30_000)
            // Prices include tax, so the VAT is inside the total, not on top.
            ->where('quote.totals.totalCents', 330_000)
            ->where('quote.totals.deliveryMethod', 'delivery')
            ->where('deliveryMethod', 'delivery')
            ->has('addresses')
            ->has('deliveryMethods')
            ->has('paymentMethods')
            ->has('breadcrumbs', 2));
});

test('the shopper saved addresses reach the page', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 10]);
    $address = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('addresses', 1)
            ->where('addresses.0.id', $address->id));
});

test('a coupon held in session is priced into the quote', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create([
        'price' => 300_000,
        'sale_price' => null,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)
        ->post(route('checkout.coupon.store'), ['code' => $coupon->code]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quote.totals.couponCode', $coupon->code)
            ->where('quote.totals.discountCents', 50_000)
            ->where('quote.totals.totalCents', 280_000));
});

test('asking for collection prices the order without delivery', function () {
    $product = Product::factory()->published()->create([
        'price' => 300_000,
        'sale_price' => null,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['delivery' => 'pickup']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('deliveryMethod', 'pickup')
            ->where('quote.totals.deliveryMethod', 'pickup')
            ->where('quote.totals.shippingCents', 0)
            ->where('quote.totals.totalCents', 300_000));
});

test('collection is refused when the store has it switched off', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->local_pickup_enabled = false;
    $shipping->save();

    $product = Product::factory()->published()->create([
        'price' => 300_000,
        'sale_price' => null,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['delivery' => 'pickup']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('deliveryMethod', 'delivery')
            ->where('quote.totals.shippingCents', 30_000));
});

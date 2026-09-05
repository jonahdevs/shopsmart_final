<?php

use App\Enums\StockStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\PaymentSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Illuminate\Testing\TestResponse;

/**
 * Everything that has to stop an order being written.
 *
 * The shared invariant across this file: a refused checkout leaves NOTHING
 * behind — no order row, no coupon budget spent, and a cart the shopper can go
 * back to and fix.
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

    // An offline method, so nothing in this file needs a gateway: what is under
    // test is the refusal, not how the money would have been taken.
    $payments = app(PaymentSettings::class);
    $payments->paystack_enabled = false;
    $payments->bank_transfer_enabled = false;
    $payments->cash_on_delivery_enabled = true;
    $payments->save();

    $this->customer = User::factory()->create();
    $this->address = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);

    $this->product = Product::factory()->published()->create([
        'price' => 300_000,
        'sale_price' => null,
        'stock_quantity' => 10,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    /** Put the shared product in the cart. Cart subtotal is 300,000 per unit. */
    $this->fillCart = fn (int $quantity = 1) => $this->actingAs($this->customer)
        ->post(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => $quantity]);

    /** Try to pay for the cart exactly as it stands. */
    $this->placeOrder = fn (array $payload = []): TestResponse => $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'delivery_method' => 'delivery',
            'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery',
            'quoted_total_cents' => 330_000,
            ...$payload,
        ]);

    /** Try to pay with a code held against the session, as the page would. */
    $this->placeOrderWithCoupon = fn (Coupon $coupon): TestResponse => $this
        ->withSession(['storefront.coupon' => $coupon->code])
        ->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'delivery_method' => 'delivery',
            'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery',
            'quoted_total_cents' => 330_000,
        ]);
});

test('an empty cart cannot be paid for', function () {
    ($this->placeOrder)()->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

test('stock that ran out between rendering and submitting blocks the order', function () {
    ($this->fillCart)(3);

    // Someone else bought the last of it while this shopper was deciding.
    $this->product->update(['stock_quantity' => 0, 'stock_status' => StockStatus::OutOfStock]);

    ($this->placeOrder)(['quoted_total_cents' => 930_000])
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        // The cart is left exactly as it was so the shopper can go back to it.
        ->and(session('storefront.cart'))->toHaveCount(1)
        ->and(session('storefront.cart')[(string) $this->product->id]['quantity'])->toBe(3);
});

test('a subtotal under the store minimum blocks the order', function () {
    $settings = app(CheckoutSettings::class);
    $settings->min_order_value_cents = 500_000;
    $settings->save();

    ($this->fillCart)();

    ($this->placeOrder)()->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

test('a total that moved while the shopper was checking out is refused', function () {
    ($this->fillCart)();

    // The catalog moved after the page was rendered.
    $this->product->update(['price' => 500_000]);

    ($this->placeOrder)()
        ->assertSessionHasErrors('quoted_total_cents');

    expect(Order::count())->toBe(0);
});

test('a delivery order with nowhere to deliver to is refused', function () {
    ($this->fillCart)();

    ($this->placeOrder)(['address_id' => null])
        ->assertSessionHasErrors('address_id');

    expect(Order::count())->toBe(0);
});

test('an address belonging to another shopper is refused', function () {
    ($this->fillCart)();

    $stranger = Address::factory()->create(['user_id' => User::factory()->create()->id]);

    ($this->placeOrder)(['address_id' => $stranger->id])
        ->assertSessionHasErrors('address_id');

    expect(Order::count())->toBe(0);
});

test('an expired coupon refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->expired()->create();

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    expect(Order::count())->toBe(0)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('a coupon that has not started yet refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->notYetStarted()->create();

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    expect(Order::count())->toBe(0)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('a switched off coupon refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->inactive()->create();

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    expect(Order::count())->toBe(0)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('a fully redeemed coupon refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->exhausted()->create();

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    expect(Order::count())->toBe(0)
        ->and($coupon->fresh()->used_count)->toBe(5);
});

test('a coupon the shopper has already used refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->oncePerCustomer()->create(['used_count' => 1]);

    CouponUse::factory()->create([
        'coupon_id' => $coupon->id,
        'user_id' => $this->customer->id,
        'order_id' => Order::factory()->create(['user_id' => $this->customer->id])->id,
    ]);

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    // The order created above to carry the earlier redemption is the only one.
    expect(Order::count())->toBe(1)
        ->and($coupon->fresh()->used_count)->toBe(1);
});

test('a coupon whose minimum subtotal is not met refuses the order', function () {
    ($this->fillCart)();

    $coupon = Coupon::factory()->requiringSubtotal(1_000_000)->create();

    ($this->placeOrderWithCoupon)($coupon)->assertSessionHasErrors('coupon');

    expect(Order::count())->toBe(0)
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('collection is refused when the store has switched it off', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->local_pickup_enabled = false;
    $shipping->save();

    ($this->fillCart)();

    ($this->placeOrder)(['delivery_method' => 'pickup', 'quoted_total_cents' => 300_000])
        ->assertSessionHasErrors('delivery_method');

    expect(Order::count())->toBe(0);
});

test('a payment method the store does not accept refuses the order', function () {
    ($this->fillCart)();

    // Paystack is switched off in this file, so the page would never have
    // offered it — and the same list that renders the choices validates the one
    // that comes back.
    ($this->placeOrder)(['payment_method' => 'paystack'])
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

test('an order with no payment method chosen is refused', function () {
    ($this->fillCart)();

    ($this->placeOrder)(['payment_method' => null])
        ->assertSessionHasErrors(['payment_method' => 'The payment method field is required.']);

    expect(Order::count())->toBe(0);
});

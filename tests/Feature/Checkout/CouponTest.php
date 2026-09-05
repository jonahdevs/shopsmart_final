<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\CheckoutSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Database\Seeders\CouponSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * The two halves of a discount code: whether it may be used, and what it is
 * worth. Both live on the model, so the wording a shopper sees when a code is
 * rejected at the cart is the same wording they see if it lapses before they pay.
 *
 * Also covers the session end of it — a code is held by code alone, never by
 * the amount it was worth when it was applied.
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

    $this->customer = User::factory()->create();
});

test('a coupon with nothing wrong with it validates', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();

    expect($coupon->validateFor($this->customer, 500_000))->toBeNull()
        ->and($coupon->validateFor(null, 500_000))->toBeNull();
});

test('every rejection reason reads differently', function () {
    $used = Coupon::factory()->oncePerCustomer()->create();
    CouponUse::factory()->create([
        'coupon_id' => $used->id,
        'user_id' => $this->customer->id,
        'order_id' => Order::factory()->create(['user_id' => $this->customer->id])->id,
    ]);

    $reasons = [
        'inactive' => Coupon::factory()->inactive()->create()->validateFor($this->customer, 500_000),
        'not yet started' => Coupon::factory()->notYetStarted()->create()->validateFor($this->customer, 500_000),
        'expired' => Coupon::factory()->expired()->create()->validateFor($this->customer, 500_000),
        'under the minimum' => Coupon::factory()->requiringSubtotal(1_000_000)->create()->validateFor($this->customer, 500_000),
        'fully redeemed' => Coupon::factory()->exhausted()->create()->validateFor($this->customer, 500_000),
        'already used by this shopper' => $used->validateFor($this->customer, 500_000),
    ];

    // "Spend a bit more" and "this expired" have different remedies, so a
    // shopper must never be shown the same sentence for both.
    expect(array_filter($reasons))->toHaveCount(6)
        ->and(array_unique($reasons))->toHaveCount(6);
});

test('a per-customer limit only counts that customer', function () {
    $coupon = Coupon::factory()->oncePerCustomer()->create();
    $stranger = User::factory()->create();

    CouponUse::factory()->create([
        'coupon_id' => $coupon->id,
        'user_id' => $stranger->id,
        'order_id' => Order::factory()->create(['user_id' => $stranger->id])->id,
    ]);

    expect($coupon->validateFor($stranger, 500_000))->not->toBeNull()
        ->and($coupon->validateFor($this->customer, 500_000))->toBeNull();
});

test('a fixed discount can never exceed the subtotal', function () {
    $coupon = Coupon::factory()->fixed(500_000)->create();

    expect($coupon->discountFor(200_000))->toBe(200_000)
        ->and($coupon->discountFor(800_000))->toBe(500_000)
        ->and($coupon->discountFor(0))->toBe(0);
});

test('a percentage discount is capped by its maximum', function () {
    $capped = Coupon::factory()->percent(50, maxDiscountCents: 100_000)->create();
    $uncapped = Coupon::factory()->percent(10)->create();

    expect($capped->discountFor(1_000_000))->toBe(100_000)
        // Below the cap the percentage is what applies.
        ->and($capped->discountFor(100_000))->toBe(50_000)
        ->and($uncapped->discountFor(1_000_000))->toBe(100_000);
});

test('a code applied at checkout is held against the session', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create(['price' => 300_000, 'sale_price' => null]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);

    // Typed in lowercase, as a shopper would.
    $this->actingAs($this->customer)
        ->post(route('checkout.coupon.store'), ['code' => mb_strtolower($coupon->code)])
        ->assertSessionHasNoErrors();

    expect(session('storefront.coupon'))->toBe($coupon->code);
});

test('a code can be taken off again', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();

    $this->actingAs($this->customer)->post(route('checkout.coupon.store'), ['code' => $coupon->code]);
    $this->actingAs($this->customer)->delete(route('checkout.coupon.destroy'));

    expect(session('storefront.coupon'))->toBeNull();
});

test('a code nobody issued is rejected', function () {
    $this->actingAs($this->customer)
        ->post(route('checkout.coupon.store'), ['code' => 'NOTACODE'])
        ->assertSessionHasErrors('code');

    expect(session('storefront.coupon'))->toBeNull();
});

test('a code that cannot be redeemed is rejected when it is applied', function () {
    $coupon = Coupon::factory()->expired()->create();

    $this->actingAs($this->customer)
        ->post(route('checkout.coupon.store'), ['code' => $coupon->code])
        ->assertSessionHasErrors('code');

    expect(session('storefront.coupon'))->toBeNull();
});

test('the demo codes seed into usable coupons', function () {
    // Muted events, exactly as DatabaseSeeder runs it.
    Model::withoutEvents(fn () => $this->seed(CouponSeeder::class));

    $welcome = Coupon::query()->where('code', CouponSeeder::WELCOME_CODE)->sole();
    $bulk = Coupon::query()->where('code', CouponSeeder::BULK_CODE)->sole();

    expect($welcome->type)->toBe(CouponType::Fixed)
        ->and($welcome->validateFor($this->customer, 300_000))->toBeNull()
        ->and($welcome->validateFor($this->customer, 100_000))->not->toBeNull()
        ->and($welcome->discountFor(300_000))->toBe(50_000)
        ->and($bulk->type)->toBe(CouponType::Percent)
        ->and($bulk->discountFor(2_000_000))->toBe(200_000)
        // Capped, however large the order gets.
        ->and($bulk->discountFor(100_000_000))->toBe(500_000);
});

test('re-seeding the demo codes does not erase what has been redeemed', function () {
    Model::withoutEvents(fn () => $this->seed(CouponSeeder::class));

    Coupon::query()->where('code', CouponSeeder::WELCOME_CODE)->update(['used_count' => 7]);

    Model::withoutEvents(fn () => $this->seed(CouponSeeder::class));

    expect(Coupon::query()->where('code', CouponSeeder::WELCOME_CODE)->sole()->used_count)->toBe(7)
        ->and(Coupon::count())->toBe(2);
});

test('clearing the cart takes the coupon with it', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create(['price' => 300_000, 'sale_price' => null]);

    $this->actingAs($this->customer)->post(route('cart.store'), ['product_id' => $product->id]);
    $this->actingAs($this->customer)->post(route('checkout.coupon.store'), ['code' => $coupon->code]);

    expect(session('storefront.coupon'))->toBe($coupon->code);

    $this->actingAs($this->customer)->delete(route('cart.clear'));

    // A code left behind on an empty cart would silently reapply to whatever
    // the shopper puts in next.
    expect(session('storefront.cart', []))->toBe([])
        ->and(session('storefront.coupon'))->toBeNull();
});

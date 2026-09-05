<?php

use App\Enums\DeliveryMethod;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\TaxClass;
use App\Settings\CheckoutSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use App\Support\CheckoutPricer;

/**
 * The arithmetic every price in the storefront ends up depending on.
 *
 * The cases that matter most are the interactions: discount against tax,
 * discount against the free-shipping threshold, and rounding against the
 * requirement that per-line figures add up to the order-level ones.
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
    $shipping->save();

    $checkout = app(CheckoutSettings::class);
    $checkout->min_order_value_cents = 0;
    $checkout->save();
});

/**
 * Build session-shaped cart lines for the given products.
 *
 * @param  array<int, array{0: Product, 1: int}>  $pairs
 * @return array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}>
 */
function lines(array $pairs): array
{
    $lines = [];

    foreach ($pairs as [$product, $quantity]) {
        $lines[(string) $product->id] = [
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => $quantity,
            'unit_price_cents' => $product->effectivePriceCents() ?? 0,
        ];
    }

    return $lines;
}

function pricer(): CheckoutPricer
{
    return app(CheckoutPricer::class);
}

test('a tax-inclusive line extracts its vat without changing the total', function () {
    $product = Product::factory()->published()->create([
        'price' => 116_000,
        'sale_price' => null,
        'tax_class_id' => $this->standardVat->id,
    ]);

    $quote = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->totals->subtotalCents)->toBe(116_000)
        ->and($quote->totals->taxCents)->toBe(16_000)
        ->and($quote->totals->totalCents)->toBe(116_000);
});

test('a tax-exclusive line adds its vat on top', function () {
    $tax = app(TaxSettings::class);
    $tax->prices_include_tax = false;
    $tax->save();

    $product = Product::factory()->published()->create([
        'price' => 100_000,
        'sale_price' => null,
        'tax_class_id' => $this->standardVat->id,
    ]);

    $quote = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->totals->subtotalCents)->toBe(100_000)
        ->and($quote->totals->taxCents)->toBe(16_000)
        ->and($quote->totals->totalCents)->toBe(116_000);
});

test('a coupon reduces the vat charged on a tax-exclusive store', function () {
    // The defect this whole design exists to avoid: taxing the subtotal before
    // the discount leaves the shopper paying tax on money they never spent.
    $tax = app(TaxSettings::class);
    $tax->prices_include_tax = false;
    $tax->save();

    $product = Product::factory()->published()->create([
        'price' => 100_000,
        'sale_price' => null,
        'tax_class_id' => $this->standardVat->id,
    ]);

    $coupon = Coupon::factory()->fixed(50_000)->create();

    $quote = pricer()->quote(lines([[$product, 1]]), $coupon, DeliveryMethod::Pickup);

    expect($quote->totals->discountCents)->toBe(50_000)
        // 16% of the discounted 50,000, not of the full 100,000.
        ->and($quote->totals->taxCents)->toBe(8_000)
        ->and($quote->totals->totalCents)->toBe(58_000);
});

test('per-line tax and discount add up to the order totals', function () {
    $a = Product::factory()->published()->create(['price' => 33_333, 'sale_price' => null, 'tax_class_id' => $this->standardVat->id]);
    $b = Product::factory()->published()->create(['price' => 66_667, 'sale_price' => null, 'tax_class_id' => $this->standardVat->id]);
    $c = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null, 'tax_class_id' => $this->standardVat->id]);

    // 10 cents cannot divide evenly three ways, so this exercises the
    // largest-remainder allocation rather than a clean split.
    $coupon = Coupon::factory()->fixed(10)->create();

    $quote = pricer()->quote(lines([[$a, 1], [$b, 1], [$c, 1]]), $coupon, DeliveryMethod::Pickup);

    $lineDiscounts = array_sum(array_map(fn ($line) => $line->discountCents, $quote->lines));
    $lineTax = array_sum(array_map(fn ($line) => $line->taxCents, $quote->lines));

    expect($lineDiscounts)->toBe($quote->totals->discountCents)
        ->and($lineTax)->toBe($quote->totals->taxCents);
});

test('a fixed discount can never exceed the subtotal', function () {
    $product = Product::factory()->published()->create(['price' => 20_000, 'sale_price' => null]);

    $coupon = Coupon::factory()->fixed(500_000)->create();

    $quote = pricer()->quote(lines([[$product, 1]]), $coupon, DeliveryMethod::Pickup);

    expect($quote->totals->discountCents)->toBe(20_000)
        ->and($quote->totals->totalCents)->toBe(0);
});

test('a percentage discount is capped by its maximum', function () {
    $product = Product::factory()->published()->create(['price' => 1_000_000, 'sale_price' => null]);

    $coupon = Coupon::factory()->percent(50, maxDiscountCents: 100_000)->create();

    $quote = pricer()->quote(lines([[$product, 1]]), $coupon, DeliveryMethod::Pickup);

    expect($quote->totals->discountCents)->toBe(100_000);
});

test('delivery is free once the discounted subtotal clears the threshold', function () {
    $product = Product::factory()->published()->create(['price' => 5_000_000, 'sale_price' => null]);

    $full = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Delivery);

    expect($full->totals->shippingCents)->toBe(0)
        ->and($full->totals->shippingIsFree)->toBeTrue();

    // A coupon that drops the order back under the threshold reinstates the fee:
    // the threshold measures what the shopper actually pays.
    $coupon = Coupon::factory()->fixed(1_000_000)->create();

    $discounted = pricer()->quote(lines([[$product, 1]]), $coupon, DeliveryMethod::Delivery);

    expect($discounted->totals->shippingCents)->toBe(30_000);
});

test('pickup is always free and delivery under the threshold is charged', function () {
    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $pickup = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);
    $delivery = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Delivery);

    expect($pickup->totals->shippingCents)->toBe(0)
        ->and($delivery->totals->shippingCents)->toBe(30_000);
});

test('disabling tax zeroes it everywhere', function () {
    $tax = app(TaxSettings::class);
    $tax->tax_enabled = false;
    $tax->save();

    $product = Product::factory()->published()->create([
        'price' => 116_000,
        'sale_price' => null,
        'tax_class_id' => $this->standardVat->id,
    ]);

    $quote = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->totals->taxCents)->toBe(0);
});

test('a product marked not taxable is exempt while its neighbours are not', function () {
    $taxable = Product::factory()->published()->create([
        'price' => 116_000, 'sale_price' => null, 'is_taxable' => true, 'tax_class_id' => $this->standardVat->id,
    ]);
    $exempt = Product::factory()->published()->create([
        'price' => 116_000, 'sale_price' => null, 'is_taxable' => false, 'tax_class_id' => $this->standardVat->id,
    ]);

    $quote = pricer()->quote(lines([[$taxable, 1], [$exempt, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->lines[0]->taxCents)->toBe(16_000)
        ->and($quote->lines[1]->taxCents)->toBe(0)
        ->and($quote->totals->taxCents)->toBe(16_000);
});

test('the quote prices from the catalog, not from what the cart captured', function () {
    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $cart = lines([[$product, 1]]);

    // The shopper added it at 100,000; the catalog has since moved.
    $product->update(['price' => 250_000]);

    $quote = pricer()->quote($cart, null, DeliveryMethod::Pickup);

    expect($cart[(string) $product->id]['unit_price_cents'])->toBe(100_000)
        ->and($quote->totals->subtotalCents)->toBe(250_000);
});

test('a sale price is what the shopper is charged', function () {
    $product = Product::factory()->published()->create(['price' => 200_000, 'sale_price' => 150_000]);

    $quote = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->totals->subtotalCents)->toBe(150_000);
});

test('a line short of stock raises a blocker rather than being trimmed', function () {
    $product = Product::factory()->published()->create([
        'price' => 100_000, 'sale_price' => null, 'stock_quantity' => 1, 'allow_backorder' => false,
    ]);

    $quote = pricer()->quote(lines([[$product, 3]]), null, DeliveryMethod::Pickup);

    expect($quote->blockers)->toHaveCount(1)
        ->and($quote->isPlaceable())->toBeFalse()
        // The line is still priced, so the shopper can see what they asked for.
        ->and($quote->lines)->toHaveCount(1);
});

test('a subtotal under the store minimum blocks the order', function () {
    $checkout = app(CheckoutSettings::class);
    $checkout->min_order_value_cents = 500_000;
    $checkout->save();

    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $quote = pricer()->quote(lines([[$product, 1]]), null, DeliveryMethod::Pickup);

    expect($quote->meetsMinimum)->toBeFalse()
        ->and($quote->isPlaceable())->toBeFalse();
});

test('an empty cart prices to zero and is not placeable', function () {
    $quote = pricer()->quote([], null, DeliveryMethod::Pickup);

    expect($quote->lines)->toBe([])
        ->and($quote->totals->totalCents)->toBe(0)
        ->and($quote->isPlaceable())->toBeFalse();
});

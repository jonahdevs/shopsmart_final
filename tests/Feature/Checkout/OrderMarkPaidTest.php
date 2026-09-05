<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * The one transition that turns a placed order into a sale.
 *
 * Settling has to be exactly-once: a browser verify call and a gateway webhook
 * routinely arrive for the same payment, and taking the stock or redeeming the
 * coupon twice is money. The other half of this file is the stock STATUS,
 * which {@see Product::isInStock()} reads on its own — a product sitting at a
 * quantity of zero while still marked "in stock" would keep on selling.
 */
beforeEach(function () {
    $this->customer = User::factory()->create();
});

test('an order settles once and refuses to settle again', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    expect($order->markPaid('paystack'))->toBeTrue()
        ->and($order->markPaid('paystack'))->toBeFalse();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment_status)->toBe(PaymentStatus::Success)
        ->and($order->payment_method)->toBe('paystack')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->isPaid())->toBeTrue()
        ->and($order->awaitsPayment())->toBeFalse();
});

test('settling twice takes the stock once and redeems the coupon once', function () {
    $coupon = Coupon::factory()->fixed(50_000)->create();
    $product = Product::factory()->published()->create([
        'stock_quantity' => 10,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'coupon_id' => $coupon->id,
        'coupon_code' => $coupon->code,
        'discount_cents' => 50_000,
    ]);

    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeTrue()
        ->and($order->markPaid('paystack'))->toBeFalse()
        // The two inner guards are what a replayed webhook actually reaches.
        ->and($order->deductStock())->toBeFalse()
        ->and($order->recordCouponUse())->toBeFalse();

    expect($product->fresh()->stock_quantity)->toBe(6)
        ->and($coupon->fresh()->used_count)->toBe(1);

    $this->assertDatabaseCount('coupon_uses', 1);
    $this->assertDatabaseHas('coupon_uses', [
        'coupon_id' => $coupon->id,
        'order_id' => $order->id,
        'user_id' => $this->customer->id,
        'discount_cents' => 50_000,
    ]);
});

test('an order without a coupon records no redemption', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'discount_cents' => 0]);

    expect($order->recordCouponUse())->toBeFalse();

    $this->assertDatabaseCount('coupon_uses', 0);
});

test('a product that reaches zero is marked out of stock', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 3,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 3)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    $product->refresh();

    // isInStock() reads only the status, so leaving it "in stock" at a quantity
    // of zero would keep a sold-out product on sale.
    expect($product->stock_quantity)->toBe(0)
        ->and($product->stock_status)->toBe(StockStatus::OutOfStock)
        ->and($product->isInStock())->toBeFalse();
});

test('a product with stock left stays in stock', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 5,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 2)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    $product->refresh();

    expect($product->stock_quantity)->toBe(3)
        ->and($product->stock_status)->toBe(StockStatus::InStock);
});

test('a product with untracked stock is left alone', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => null,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 4)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    $product->refresh();

    expect($product->stock_quantity)->toBeNull()
        ->and($product->stock_status)->toBe(StockStatus::InStock);
});

test('a backorderable product stays in stock at zero', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 2,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => true,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 2)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    $product->refresh();

    expect($product->stock_quantity)->toBe(0)
        ->and($product->stock_status)->toBe(StockStatus::InStock)
        ->and($product->isInStock())->toBeTrue();
});

test('a line that oversells floors at zero rather than going negative', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 1,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 5)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    $product->refresh();

    expect($product->stock_quantity)->toBe(0)
        ->and($product->stock_status)->toBe(StockStatus::OutOfStock);
});

test('a variant line takes the stock off the variant, not its parent', function () {
    $product = Product::factory()->published()->variable()->create([
        'stock_quantity' => 50,
        'stock_status' => StockStatus::InStock,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 400_000,
        'stock_quantity' => 5,
        'stock_status' => StockStatus::InStock,
        'allow_backorder' => false,
    ]);

    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 5, $variant)->create(['order_id' => $order->id]);

    $order->markPaid('paystack');

    expect($variant->fresh()->stock_quantity)->toBe(0)
        ->and($variant->fresh()->stock_status)->toBe(StockStatus::OutOfStock)
        ->and($product->fresh()->stock_quantity)->toBe(50)
        ->and($product->fresh()->stock_status)->toBe(StockStatus::InStock);
});

test('a cancelled order cannot be settled', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 10,
        'stock_status' => StockStatus::InStock,
    ]);

    $order = Order::factory()->cancelled()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 3)->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeFalse();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->paid_at)->toBeNull()
        ->and($order->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('an order already settled elsewhere cannot be settled again', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 10,
        'stock_status' => StockStatus::InStock,
    ]);

    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);
    OrderItem::factory()->forProduct($product, 3)->create(['order_id' => $order->id]);

    expect($order->markPaid('paystack'))->toBeFalse()
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

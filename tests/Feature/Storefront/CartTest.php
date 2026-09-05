<?php

use App\Enums\ProductVisibility;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The cart: what a shopper is allowed to put in it, what happens to a quantity
 * the stock cannot honour, what the captured price protects against, and how
 * the session copy and the persisted copy stay in step.
 */
// These assert page props, not markup, so they do not depend on a JS build
// having been run — the root template would otherwise resolve every page
// component through the Vite manifest.
beforeEach(function () {
    $this->withoutVite();
});

test('a guest cart survives across requests', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Cart')
            ->has('cart.items', 1)
            ->where('cart.items.0.productId', $product->id)
            ->where('cart.items.0.quantity', 2)
            ->where('cart.itemCount', 2));
});

test('adding the same line again tops it up rather than duplicating it', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 10]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 1)
            ->where('cart.items.0.quantity', 5));
});

test('the cart subtotal is the sum of the captured line prices', function () {
    $product = Product::factory()->published()->create(['price' => 150_000, 'sale_price' => null]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.unitPriceCents', 150_000)
            ->where('cart.items.0.lineTotalCents', 450_000)
            ->where('cart.subtotalCents', 450_000)
            ->where('cart.subtotalFormatted', money(450_000)));
});

test('a line keeps the price it was added at when the catalog price changes', function () {
    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $product->update(['price' => 250_000]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.unitPriceCents', 100_000)
            ->where('cart.items.0.currentUnitPriceCents', 250_000)
            ->where('cart.items.0.priceChanged', true)
            ->where('cart.subtotalCents', 100_000)
            ->where('cart.hasPriceChanges', true));
});

test('topping up a line does not re-price the units already in it', function () {
    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $product->update(['price' => 250_000]);

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.quantity', 2)
            ->where('cart.items.0.unitPriceCents', 100_000));
});

test('a sale price is what gets captured', function () {
    $product = Product::factory()->published()->create(['price' => 200_000, 'sale_price' => 120_000]);

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.unitPriceCents', 120_000));
});

test('the quantity is clamped to the stock on hand', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 3,
        'allow_backorder' => false,
    ]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 99]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.quantity', 3)
            ->where('cart.items.0.maxQuantity', 3));
});

test('a backorderable product has no quantity ceiling', function () {
    $product = Product::factory()->published()->create([
        'stock_quantity' => 1,
        'allow_backorder' => true,
    ]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 20]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.quantity', 20)
            ->where('cart.items.0.maxQuantity', null));
});

test('the quantity is raised to the product minimum order quantity', function () {
    $product = Product::factory()->published()->create([
        'min_order_quantity' => 5,
        'stock_quantity' => 50,
    ]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.quantity', 5));
});

test('a draft product cannot be added to the cart', function () {
    $product = Product::factory()->draft()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');

    expect(session('storefront.cart', []))->toBe([]);
});

test('a hidden product cannot be added to the cart', function () {
    $product = Product::factory()->published()->hidden()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a soft deleted product cannot be added to the cart', function () {
    $product = Product::factory()->published()->create();
    $product->delete();

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('an out of stock product with no backorder cannot be added to the cart', function () {
    $product = Product::factory()->published()->outOfStock()->create(['allow_backorder' => false]);

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a price on application product cannot be added to the cart', function () {
    $product = Product::factory()->published()->withoutPrice()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a variable product cannot be added without choosing a variant', function () {
    $product = Product::factory()->published()->variable()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a variant line prices off the variant', function () {
    $product = Product::factory()->published()->variable()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 400_000,
        'sale_price' => 320_000,
    ]);

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.variantId', $variant->id)
            ->where('cart.items.0.unitPriceCents', 320_000)
            ->where('cart.items.0.lineTotalCents', 640_000));
});

test('a variant belonging to another product is rejected', function () {
    $product = Product::factory()->published()->variable()->create();
    $foreignVariant = ProductVariant::factory()->create();

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'variant_id' => $foreignVariant->id,
    ])->assertSessionHasErrors('variant_id');
});

test('an inactive variant is rejected', function () {
    $product = Product::factory()->published()->variable()->create();
    $variant = ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
    ])->assertSessionHasErrors('product_id');
});

test('the same product in two variants is two separate lines', function () {
    $product = Product::factory()->published()->variable()->create();
    $small = ProductVariant::factory()->create(['product_id' => $product->id]);
    $large = ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $small->id]);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $large->id]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('cart.items', 2));
});

test('a line can be set to an exact quantity', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
    $this->patch(route('cart.update'), ['product_id' => $product->id, 'quantity' => 7]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.quantity', 7));
});

test('setting a line to zero removes it', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);
    $this->patch(route('cart.update'), ['product_id' => $product->id, 'quantity' => 0]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 0)
            ->where('cart.isEmpty', true));
});

test('a line can be removed', function () {
    $product = Product::factory()->published()->create();
    $other = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);
    $this->post(route('cart.store'), ['product_id' => $other->id]);

    $this->delete(route('cart.destroy'), ['product_id' => $product->id]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 1)
            ->where('cart.items.0.productId', $other->id));
});

test('a line whose product is soft deleted afterwards drops out of the cart', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $product->delete();

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('cart.isEmpty', true));

    expect(session('storefront.cart'))->toBe([]);
});

test('a line whose product is hidden afterwards drops out of the cart', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $product->update(['visibility' => ProductVisibility::Hidden]);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('cart.isEmpty', true));
});

test('the cart can be cleared', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->delete(route('cart.clear'))->assertRedirect(route('cart.index'));

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('cart.isEmpty', true));
});

test('the cart count is shared on every storefront response', function () {
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->get(route('catalog'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.shopper.cartCount', 3));
});

test('an authenticated cart is mirrored into the database', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price' => 100_000, 'sale_price' => null]);

    $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 2,
        'unit_price_cents' => 100_000,
    ]);
});

test('removing a line removes it from the persisted cart too', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create();

    $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id]);
    $this->actingAs($user)->delete(route('cart.destroy'), ['product_id' => $product->id]);

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

test('a guest never gets a persisted cart row', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->assertDatabaseCount('carts', 0);
    $this->assertDatabaseCount('cart_items', 0);
});

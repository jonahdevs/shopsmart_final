<?php

use App\Enums\SavedProductList;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SavedProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Logging in is the one moment the session copy and the persisted copy of a
 * shopper's cart genuinely diverge. These cover how they are reconciled — and,
 * above all, that doing it twice changes nothing.
 */
// These assert page props, not markup, so they do not depend on a JS build
// having been run — the root template would otherwise resolve every page
// component through the Vite manifest.
beforeEach(function () {
    $this->withoutVite();
});

test('a guest cart is carried into the account on login', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
});

test('an overlapping line keeps the larger quantity rather than the sum', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->forProduct($product, quantity: 5)->create(['cart_id' => $cart->id]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect(CartItem::query()->where('cart_id', $cart->id)->count())->toBe(1);
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 5]);
});

test('a guest line larger than the saved one wins', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->forProduct($product, quantity: 1)->create(['cart_id' => $cart->id]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 4]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 4]);
});

test('logging in a second time does not inflate a merged line', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock_quantity' => 50]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->forProduct($product, quantity: 3)->create(['cart_id' => $cart->id]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect(CartItem::query()->where('cart_id', $cart->id)->count())->toBe(1);
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 3]);
});

test('the saved cart is rehydrated into the session so the header count is right', function () {
    $user = User::factory()->create();
    $saved = Product::factory()->published()->create(['stock_quantity' => 50]);
    $guestOnly = Product::factory()->published()->create(['stock_quantity' => 50]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->forProduct($saved, quantity: 3)->create(['cart_id' => $cart->id]);

    $this->post(route('cart.store'), ['product_id' => $guestOnly->id, 'quantity' => 1]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->get(route('cart.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 2)
            ->where('cart.itemCount', 4)
            ->where('storefront.shopper.cartCount', 4));
});

test('a merged line keeps the price it was persisted at', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price' => 300_000, 'sale_price' => null]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price_cents' => 100_000,
    ]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'quantity' => 2,
        'unit_price_cents' => 100_000,
    ]);
});

test('a shopper who has never had a cart gets no cart row on login', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseCount('carts', 0);
});

test('the wishlist merges as a union of the saved and session lists', function () {
    $user = User::factory()->create();
    $saved = Product::factory()->published()->create();
    $guestOnly = Product::factory()->published()->create();

    SavedProduct::factory()->create([
        'user_id' => $user->id,
        'product_id' => $saved->id,
        'list' => SavedProductList::Wishlist,
        'position' => 0,
    ]);

    $this->post(route('wishlist.store'), ['product_id' => $guestOnly->id]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 2)
            ->where('products.0.id', $saved->id)
            ->where('products.1.id', $guestOnly->id));

    expect(SavedProduct::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('logging in a second time does not duplicate a merged wishlist entry', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create();

    SavedProduct::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'list' => SavedProductList::Wishlist,
    ]);

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect(SavedProduct::query()->where('user_id', $user->id)->count())->toBe(1);
});

<?php

use App\Enums\ProductVisibility;
use App\Enums\SavedProductList;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The wishlist: what may be saved, that saving is idempotent, that saved order
 * is what the page renders, and that a signed-in shopper's list is mirrored.
 */
// These assert page props, not markup, so they do not depend on a JS build
// having been run — the root template would otherwise resolve every page
// component through the Vite manifest.
beforeEach(function () {
    $this->withoutVite();
});

test('a guest wishlist survives across requests and keeps its order', function () {
    $first = Product::factory()->published()->create();
    $second = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $first->id]);
    $this->post(route('wishlist.store'), ['product_id' => $second->id]);

    $this->get(route('wishlist.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Wishlist')
            ->has('products', 2)
            ->where('products.0.id', $first->id)
            ->where('products.1.id', $second->id));
});

test('saving a product already in the wishlist is a no-op', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);
    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 1));
});

test('an out of stock product can still be wishlisted', function () {
    $product = Product::factory()->published()->outOfStock()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id])
        ->assertSessionHasNoErrors();

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 1));
});

test('a draft product cannot be wishlisted', function () {
    $product = Product::factory()->draft()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a hidden product cannot be wishlisted', function () {
    $product = Product::factory()->published()->hidden()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('product_id');
});

test('a wishlisted product that is later hidden drops out of the list', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $product->update(['visibility' => ProductVisibility::Hidden]);

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));

    expect(session('storefront.wishlist'))->toBe([]);
});

test('a product can be removed from the wishlist', function () {
    $kept = Product::factory()->published()->create();
    $dropped = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $kept->id]);
    $this->post(route('wishlist.store'), ['product_id' => $dropped->id]);

    $this->delete(route('wishlist.destroy'), ['product_id' => $dropped->id]);

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $kept->id));
});

test('the wishlist can be cleared', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $this->delete(route('wishlist.clear'))->assertRedirect(route('wishlist.index'));

    $this->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));
});

test('the wishlist count and ids are shared on every storefront response', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $this->get(route('catalog'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.shopper.wishlistCount', 1)
            ->where('storefront.shopper.wishlistProductIds', [$product->id]));
});

test('an authenticated wishlist is mirrored into the database', function () {
    $user = User::factory()->create();
    $first = Product::factory()->published()->create();
    $second = Product::factory()->published()->create();

    $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $first->id]);
    $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $second->id]);

    $this->assertDatabaseHas('saved_products', [
        'user_id' => $user->id,
        'product_id' => $first->id,
        'list' => SavedProductList::Wishlist->value,
        'position' => 0,
    ]);
    $this->assertDatabaseHas('saved_products', [
        'user_id' => $user->id,
        'product_id' => $second->id,
        'list' => SavedProductList::Wishlist->value,
        'position' => 1,
    ]);
});

test('removing from an authenticated wishlist removes the persisted row', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create();

    $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $product->id]);
    $this->actingAs($user)->delete(route('wishlist.destroy'), ['product_id' => $product->id]);

    $this->assertDatabaseCount('saved_products', 0);
});

test('a guest never gets a persisted wishlist row', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('wishlist.store'), ['product_id' => $product->id]);

    $this->assertDatabaseCount('saved_products', 0);
});

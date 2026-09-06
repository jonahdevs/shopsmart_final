<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\RecentlyViewed;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The shopper's own browsing history.
 *
 * Written by ProductController on every product view; read back here newest
 * first. A product that has since been unpublished drops out — the history is a
 * shortcut back to the catalog, not an archive.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 6 page components are built by another agent from the props
    // asserted here.
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->customer = User::factory()->create();
});

test('the page lists this customer history, most recent first', function () {
    $older = Product::factory()->published()->create();
    $newer = Product::factory()->published()->create();

    RecentlyViewed::record($this->customer, $older);
    $this->travel(1)->minutes();
    RecentlyViewed::record($this->customer, $newer);

    $this->actingAs($this->customer)
        ->get(route('account.recently-viewed'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('account/RecentlyViewed')
            ->has('products', 2)
            ->where('products.0.id', $newer->id)
            ->where('products.1.id', $older->id)
            ->has('breadcrumbs', 2));
});

test('another shopper history is not mixed in', function () {
    $mine = Product::factory()->published()->create();
    $theirs = Product::factory()->published()->create();

    RecentlyViewed::record($this->customer, $mine);
    RecentlyViewed::record(User::factory()->create(), $theirs);

    $this->actingAs($this->customer)
        ->get(route('account.recently-viewed'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $mine->id));
});

test('a product that has been unpublished drops out of the history', function () {
    $product = Product::factory()->published()->create();

    RecentlyViewed::record($this->customer, $product);

    $product->update(['status' => ProductStatus::Draft]);

    $this->actingAs($this->customer)
        ->get(route('account.recently-viewed'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));
});

test('a shopper who has viewed nothing gets an empty page', function () {
    $this->actingAs($this->customer)
        ->get(route('account.recently-viewed'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));
});

test('a guest cannot reach the history', function () {
    $this->get(route('account.recently-viewed'))->assertRedirect(route('login'));
});

<?php

use App\Enums\SavedProductList;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\User;
use App\Support\StorefrontSession;
use Inertia\Testing\AssertableInertia;

/**
 * The compare tray: the cap and what it does when exceeded, and the aligned
 * attribute matrix the compare table renders.
 */
// These assert page props, not markup, so they do not depend on a JS build
// having been run — the root template would otherwise resolve every page
// component through the Vite manifest.
beforeEach(function () {
    $this->withoutVite();
});

test('products can be compared in the order they were added', function () {
    $first = Product::factory()->published()->create();
    $second = Product::factory()->published()->create();

    $this->post(route('compare.store'), ['product_id' => $first->id]);
    $this->post(route('compare.store'), ['product_id' => $second->id]);

    $this->get(route('compare.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Compare')
            ->has('compare.products', 2)
            ->where('compare.products.0.id', $first->id)
            ->where('compare.products.1.id', $second->id)
            ->where('compare.limit', StorefrontSession::COMPARE_LIMIT));
});

test('the compare tray drops its oldest entry once it is full', function () {
    $products = Product::factory()->published()->count(StorefrontSession::COMPARE_LIMIT + 1)->create();

    foreach ($products as $product) {
        $this->post(route('compare.store'), ['product_id' => $product->id]);
    }

    $this->get(route('compare.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('compare.products', StorefrontSession::COMPARE_LIMIT)
            ->where('compare.products.0.id', $products[1]->id));
});

test('the compare table aligns each attribute across the products', function () {
    $material = Attribute::factory()->create(['name' => 'Material']);
    $voltage = Attribute::factory()->create(['name' => 'Voltage']);

    $first = Product::factory()->published()->create();
    $second = Product::factory()->published()->create();

    ProductAttribute::create([
        'product_id' => $first->id,
        'attribute_id' => $material->id,
        'values' => ['Steel', 'Aluminium'],
        'is_visible' => true,
    ]);
    ProductAttribute::create([
        'product_id' => $second->id,
        'attribute_id' => $voltage->id,
        'values' => ['240V'],
        'is_visible' => true,
    ]);

    $this->post(route('compare.store'), ['product_id' => $first->id]);
    $this->post(route('compare.store'), ['product_id' => $second->id]);

    $this->get(route('compare.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('compare.attributes', 2)
            ->where('compare.attributes.0.name', 'Material')
            ->where('compare.attributes.0.values', ['Steel, Aluminium', null])
            ->where('compare.attributes.1.name', 'Voltage')
            ->where('compare.attributes.1.values', [null, '240V']));
});

test('a hidden product attribute is left out of the compare table', function () {
    $attribute = Attribute::factory()->create(['name' => 'Internal Code']);
    $product = Product::factory()->published()->create();

    ProductAttribute::create([
        'product_id' => $product->id,
        'attribute_id' => $attribute->id,
        'values' => ['XYZ'],
        'is_visible' => false,
    ]);

    $this->post(route('compare.store'), ['product_id' => $product->id]);

    $this->get(route('compare.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('compare.attributes', 0));
});

test('a product can be removed from the compare tray', function () {
    $kept = Product::factory()->published()->create();
    $dropped = Product::factory()->published()->create();

    $this->post(route('compare.store'), ['product_id' => $kept->id]);
    $this->post(route('compare.store'), ['product_id' => $dropped->id]);

    $this->delete(route('compare.destroy'), ['product_id' => $dropped->id]);

    $this->get(route('compare.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('compare.products', 1)
            ->where('compare.products.0.id', $kept->id));
});

test('the compare tray can be cleared', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('compare.store'), ['product_id' => $product->id]);

    $this->delete(route('compare.clear'))->assertRedirect(route('compare.index'));

    $this->get(route('compare.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('compare.products', 0));
});

test('the compare count and ids are shared on every storefront response', function () {
    $product = Product::factory()->published()->create();

    $this->post(route('compare.store'), ['product_id' => $product->id]);

    $this->get(route('catalog'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.shopper.compareCount', 1)
            ->where('storefront.shopper.compareProductIds', [$product->id])
            ->where('storefront.shopper.compareLimit', StorefrontSession::COMPARE_LIMIT));
});

test('an authenticated compare tray is mirrored into the database', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create();

    $this->actingAs($user)->post(route('compare.store'), ['product_id' => $product->id]);

    $this->assertDatabaseHas('saved_products', [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'list' => SavedProductList::Compare->value,
    ]);
});

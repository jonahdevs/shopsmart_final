<?php

use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\CategoryPlacement;
use App\Models\Product;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

/**
 * Both storefront read models are cached with a TTL, so a missed invalidation
 * shows up as a stale storefront rather than a wrong one — which is exactly the
 * kind of bug that survives a test suite. These pin the observers that clear
 * them, so an edit is visible on the next request instead of within the hour.
 */
test('deactivating a placement drops the cached navigation', function () {
    $category = Category::factory()->create();
    $placement = CategoryPlacement::factory()
        ->forCategory($category)
        ->location(CategorySection::Navbar)
        ->create();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('storefront.navCategories', 1));

    $placement->update(['status' => CategoryStatus::Draft]);

    expect(Cache::get(StorefrontCache::NAV_CATEGORIES))->toBeNull();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('storefront.navCategories', 0));
});

test('renaming a category drops the cached navigation', function () {
    $category = Category::factory()->create(['name' => 'Kettles']);
    CategoryPlacement::factory()->forCategory($category)->location(CategorySection::Navbar)->create();

    $this->get(route('home'))->assertOk();

    $category->update(['name' => 'Electric Kettles']);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storefront.navCategories.0.name', 'Electric Kettles'));
});

test('publishing a product drops the cached category counts', function () {
    $category = Category::factory()->create();
    Product::factory()->published()->create(['primary_category_id' => $category->id]);
    $draft = Product::factory()->draft()->create(['primary_category_id' => $category->id]);

    $this->get(route('catalog'))->assertOk();

    expect(Cache::get(StorefrontCache::CATEGORY_PRODUCT_COUNTS))->toBe([$category->id => 1]);

    $draft->update(['status' => ProductStatus::Published]);

    expect(Cache::get(StorefrontCache::CATEGORY_PRODUCT_COUNTS))->toBeNull();

    $this->get(route('catalog'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categoryFacets.0.count', 2));
});

test('an edit that cannot change a total leaves the cached counts alone', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->published()->create(['primary_category_id' => $category->id]);

    $this->get(route('catalog'))->assertOk();

    // A price edit moves no product in or out of a category, and clearing on
    // every save would leave the cache cold in a store that syncs inventory.
    $product->update(['price' => 999_00]);

    expect(Cache::get(StorefrontCache::CATEGORY_PRODUCT_COUNTS))->toBe([$category->id => 1]);
});

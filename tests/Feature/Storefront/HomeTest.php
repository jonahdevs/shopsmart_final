<?php

use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\CategoryPlacement;
use App\Models\HeroSlide;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The home page paints the hero and the category grid immediately and pulls
 * the product rails in afterwards, so these tests check both halves: what is
 * in the first response, and what only arrives once the deferred props load.
 *
 * The public disk is faked so hero art never touches the real filesystem.
 */
beforeEach(function () {
    Storage::fake('public');
});

test('the home page renders live hero slides and featured categories', function () {
    $slide = HeroSlide::factory()->active()->create(['sort_order' => 1]);
    HeroSlide::factory()->expired()->create();

    $category = Category::factory()->create();
    CategoryPlacement::factory()->create([
        'category_id' => $category->id,
        'location' => CategorySection::HomePageFeatured,
        'status' => CategoryStatus::Active,
    ]);

    Product::factory()->published()->create(['primary_category_id' => $category->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Home')
            ->has('heroSlides', 1)
            ->where('heroSlides.0.id', $slide->id)
            ->has('featuredCategories', 1)
            ->where('featuredCategories.0.id', $category->id)
            ->where('featuredCategories.0.productCount', 1));
});

test('the home page ignores category placements for other locations', function () {
    $category = Category::factory()->create();
    CategoryPlacement::factory()->create([
        'category_id' => $category->id,
        'location' => CategorySection::Footer,
        'status' => CategoryStatus::Active,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('featuredCategories', 0));
});

test('the home page defers its product rails', function () {
    Product::factory()->count(3)->published()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('heroSlides')
            ->missing('newArrivals')
            ->missing('featuredProducts')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('newArrivals', 3)
                ->has('featuredProducts', 3)));
});

test('the home page rails only carry sellable stock', function () {
    $sellable = Product::factory()->published()->create(['price' => 250_000]);
    Product::factory()->published()->outOfStock()->create();
    Product::factory()->published()->withoutPrice()->create();
    Product::factory()->draft()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('newArrivals', 1)
                ->where('newArrivals.0.id', $sellable->id)
                ->where('newArrivals.0.effectivePriceFormatted', 'KES 2,500')));
});

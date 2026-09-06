<?php

use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The faceted catalog listing: what it renders, what each filter removes, how
 * it sorts, and the query budget a page of products has to stay inside.
 */
test('the catalog renders live catalog products', function () {
    $live = Product::factory()->published()->create();

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Catalog')
            ->has('products.data', 1)
            ->where('products.data.0.id', $live->id)
            ->has('filters')
            ->has('categoryFacets')
            ->has('brandFacets'));
});

test('the catalog hides products that are not published or not catalog visible', function () {
    $live = Product::factory()->published()->create();
    Product::factory()->draft()->create();
    Product::factory()->archived()->create();
    Product::factory()->scheduled(now()->addDay())->create();
    Product::factory()->published()->hidden()->create();
    Product::factory()->published()->create(['visibility' => ProductVisibility::Search]);

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $live->id));
});

test('the catalog filters by category slug', function () {
    $wanted = Category::factory()->create();
    $other = Category::factory()->create();

    $match = Product::factory()->published()->create(['primary_category_id' => $wanted->id]);
    Product::factory()->published()->create(['primary_category_id' => $other->id]);

    $this->get(route('catalog', ['cat' => [$wanted->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id)
            ->where('filters.categories.0', $wanted->slug)
            ->where('filters.hasActiveFilters', true));
});

test('the catalog expands a selected category to its whole subtree', function () {
    // A top-level category holds no products of its own, so resolving the slug
    // without walking the tree returned an empty grid for a box the sidebar
    // itself offered.
    $parent = Category::factory()->create();
    $child = Category::factory()->child($parent)->create();
    $grandchild = Category::factory()->child($child)->create();

    $inChild = Product::factory()->published()->create(['primary_category_id' => $child->id]);
    $inGrandchild = Product::factory()->published()->create(['primary_category_id' => $grandchild->id]);
    Product::factory()->published()->create(['primary_category_id' => Category::factory()->create()->id]);

    $this->get(route('catalog', ['cat' => [$parent->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 2)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === collect([$inChild->id, $inGrandchild->id])->sort()->values()->all()));
});

test('a catalog category facet counts the subtree that ticking it returns', function () {
    $parent = Category::factory()->create(['name' => 'Parent']);
    $child = Category::factory()->child($parent)->create(['name' => 'Child']);

    Product::factory()->count(2)->published()->create(['primary_category_id' => $child->id]);

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categoryFacets', 2)
            ->where('categoryFacets.0.slug', $child->slug)
            ->where('categoryFacets.0.count', 2)
            ->where('categoryFacets.1.slug', $parent->slug)
            ->where('categoryFacets.1.count', 2));

    $this->get(route('catalog', ['cat' => [$parent->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 2));
});

test('a product filed in both a parent and its child is counted once in the catalog facet', function () {
    $parent = Category::factory()->create(['name' => 'Parent']);
    $child = Category::factory()->child($parent)->create(['name' => 'Child']);

    $product = Product::factory()->published()->create(['primary_category_id' => $parent->id]);
    $product->categories()->attach($child);

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categoryFacets.1.slug', $parent->slug)
            ->where('categoryFacets.1.count', 1));

    $this->get(route('catalog', ['cat' => [$parent->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1));
});

test('catalog brand facets narrow to the selected category', function () {
    $wanted = Category::factory()->create();
    $inside = Brand::factory()->create(['name' => 'Inside']);
    $outside = Brand::factory()->create(['name' => 'Outside']);

    Product::factory()->published()->create([
        'primary_category_id' => $wanted->id,
        'brand_id' => $inside->id,
    ]);
    Product::factory()->count(3)->published()->create(['brand_id' => $outside->id]);

    $this->get(route('catalog', ['cat' => [$wanted->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('brandFacets', 1)
            ->where('brandFacets.0.id', $inside->id)
            ->where('brandFacets.0.count', 1));
});

test('the catalog matches a product joined to a category through the pivot only', function () {
    $category = Category::factory()->create();
    $match = Product::factory()->published()->create();
    $match->categories()->attach($category);

    Product::factory()->published()->create();

    $this->get(route('catalog', ['cat' => [$category->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id));
});

test('the catalog filters by brand', function () {
    $brand = Brand::factory()->create();
    $match = Product::factory()->published()->create(['brand_id' => $brand->id]);
    Product::factory()->published()->create(['brand_id' => Brand::factory()->create()->id]);

    $this->get(route('catalog', ['brand' => [$brand->id]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id));
});

test('the catalog filters by price range, reading whole KES against cents', function () {
    $cheap = Product::factory()->published()->create(['price' => 100_000]);
    $mid = Product::factory()->published()->create(['price' => 500_000]);
    $expensive = Product::factory()->published()->create(['price' => 900_000]);

    $this->get(route('catalog', ['pmin' => 2_000, 'pmax' => 6_000]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $mid->id));

    expect([$cheap->price, $expensive->price])->toBe([100_000, 900_000]);
});

test('the catalog price ceiling keeps unpriced products but a floor drops them', function () {
    $unpriced = Product::factory()->published()->withoutPrice()->create();

    $this->get(route('catalog', ['pmax' => 1_000]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $unpriced->id));

    $this->get(route('catalog', ['pmin' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
});

test('a product priced above the slider ceiling is still listed when no bound is set', function () {
    // The slider tops out at KES 6,000,000; this is KES 9,000,000. Defaulting
    // the unset upper bound to the ceiling put that filter on every listing
    // query, so this product was invisible store-wide with nothing on the page
    // to say a filter was on.
    $dearer = Product::factory()->published()->create(['price' => 900_000_000]);

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $dearer->id)
            ->where('filters.priceMax', null)
            ->where('filters.priceCeiling', 6_000_000)
            ->where('filters.hasActiveFilters', false));

    // A bound the shopper actually sets still applies.
    $this->get(route('catalog', ['pmax' => 1_000_000]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 0)
            ->where('filters.hasActiveFilters', true));
});

test('the catalog rejects a price range whose floor is above its ceiling', function () {
    $this->get(route('catalog', ['pmin' => 500_000, 'pmax' => 1_000]))
        ->assertSessionHasErrors('pmin');
});

test('the catalog filters by minimum average rating', function () {
    $good = Product::factory()->published()->create();
    Review::factory()->approved()->for($good)->create(['rating' => 5]);
    Review::factory()->approved()->for($good)->create(['rating' => 5]);

    $poor = Product::factory()->published()->create();
    Review::factory()->approved()->for($poor)->create(['rating' => 2]);

    $this->get(route('catalog', ['rating' => 4]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $good->id)
            ->where('products.data.0.ratingCount', 2)
            ->where('products.data.0.ratingAverage', fn ($average) => (float) $average === 5.0));
});

test('the catalog filters out of stock products when the shopper asks for stock only', function () {
    $inStock = Product::factory()->published()->create();
    Product::factory()->published()->outOfStock()->create();

    $this->get(route('catalog', ['stock' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $inStock->id));
});

test('the catalog search term matches name, sku and brand name', function () {
    $byName = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);
    $bySku = Product::factory()->published()->create(['sku' => 'KETTLE-99']);
    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    $byBrand = Product::factory()->published()->create(['brand_id' => $brand->id]);
    Product::factory()->published()->create(['name' => 'Toaster']);

    $this->get(route('catalog', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 3)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === collect([$byName->id, $bySku->id, $byBrand->id])->sort()->values()->all()));
});

test('a LIKE wildcard in the search term does not open the whole catalog', function () {
    Product::factory()->count(3)->published()->create();
    Product::factory()->published()->create(['name' => 'Axb Mixer']);

    // Unescaped, `%` matched every row and `a_b` matched "Axb".
    $this->get(route('catalog', ['q' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));

    $this->get(route('catalog', ['q' => 'a_b']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
});

test('the catalog filters by merchandising tag', function () {
    $tagged = Product::factory()->published()->create();
    $tagged->attachTag('Clearance');

    Product::factory()->published()->create()->attachTag('Featured');
    Product::factory()->published()->create();

    $this->get(route('catalog', ['tag' => 'Clearance']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $tagged->id));
});

test('the new arrivals filter keeps recent stock and anything pinned by the tag', function () {
    $recent = Product::factory()->published()->create();

    $pinned = Product::factory()->published()->create(['published_at' => now()->subDays(200)]);
    $pinned->attachTag('New Arrival');

    Product::factory()->published()->create(['published_at' => now()->subDays(200)]);
    Product::factory()->published()->outOfStock()->create();
    Product::factory()->published()->withoutPrice()->create();

    $this->get(route('catalog', ['arrivals' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 2)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === collect([$recent->id, $pinned->id])->sort()->values()->all()));
});

test('the catalog sorts by effective price ascending and sinks unpriced products', function () {
    $unpriced = Product::factory()->published()->withoutPrice()->create();
    $dear = Product::factory()->published()->create(['price' => 900_000]);
    $discounted = Product::factory()->published()->create(['price' => 800_000, 'sale_price' => 100_000]);

    $this->get(route('catalog', ['sort' => 'price-asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.id', $discounted->id)
            ->where('products.data.1.id', $dear->id)
            ->where('products.data.2.id', $unpriced->id));
});

test('the catalog sorts by name', function () {
    $c = Product::factory()->published()->create(['name' => 'Cooker']);
    $a = Product::factory()->published()->create(['name' => 'Apron']);
    $b = Product::factory()->published()->create(['name' => 'Blender']);

    $this->get(route('catalog', ['sort' => 'name-asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.id', $a->id)
            ->where('products.data.1.id', $b->id)
            ->where('products.data.2.id', $c->id));
});

test('the catalog rejects an unknown sort', function () {
    $this->get(route('catalog', ['sort' => 'cheapest-ever']))->assertSessionHasErrors('sort');
});

test('the catalog facets only offer categories and brands that hold products', function () {
    $stocked = Category::factory()->create(['name' => 'Stocked']);
    Category::factory()->create(['name' => 'Empty']);

    $usedBrand = Brand::factory()->create(['name' => 'Used']);
    Brand::factory()->create(['name' => 'Unused']);

    Product::factory()->published()->create([
        'primary_category_id' => $stocked->id,
        'brand_id' => $usedBrand->id,
    ]);

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categoryFacets', 1)
            ->where('categoryFacets.0.slug', $stocked->slug)
            ->where('categoryFacets.0.count', 1)
            ->has('brandFacets', 1)
            ->where('brandFacets.0.id', $usedBrand->id)
            ->where('brandFacets.0.count', 1));
});

test('page two of a filtered catalog keeps the filter and returns the remainder', function () {
    $category = Category::factory()->create();
    Product::factory()->count(30)->published()->create(['primary_category_id' => $category->id]);
    Product::factory()->count(5)->published()->create();

    $this->get(route('catalog', ['cat' => [$category->slug], 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 6)
            ->where('products.currentPage', 2)
            ->where('products.total', 30)
            ->where('products.hasMorePages', false)
            ->where('filters.categories.0', $category->slug));
});

test('a page past the end of the catalog renders a clean empty grid', function () {
    Product::factory()->count(3)->published()->create();

    $this->get(route('catalog', ['page' => 9]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 0)
            ->where('products.currentPage', 9)
            ->where('products.lastPage', 1)
            ->where('products.total', 3)
            ->where('products.hasMorePages', false));
});

test('the catalog issues a bounded number of queries for a page of products', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    Product::factory()
        ->count(30)
        ->published()
        ->create(['primary_category_id' => $category->id, 'brand_id' => $brand->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 24));

    // Fourteen at the time of writing: the paginator count, the page itself,
    // the brand and media eager loads, the two facet aggregates and their
    // lookups, the category tree the facet counts are rolled up through, the
    // store settings, and the two settings groups behind the consent gate in
    // the document head — read on a cold cache, once an hour in production, the
    // same trade the footer's social links make.
    //
    // This cap is a coarse tripwire for a page that has grown a whole new
    // dependency; the N+1 guard proper is the test below, which is what you
    // should reach for first if this one fails.
    expect($queries)->toBeLessThanOrEqual(16);
});

test('the catalog costs the same number of queries whatever the page holds', function () {
    // The real rule the budget exists to enforce: the query count must not grow
    // with the number of products rendered. Comparing a nearly empty catalog
    // against a full page catches an N+1 no matter what the absolute number is,
    // which a fixed cap cannot do once the page legitimately grows.
    $queries = 0;
    $recording = false;

    DB::listen(function () use (&$queries, &$recording): void {
        if ($recording) {
            $queries++;
        }
    });

    $measure = function () use (&$queries, &$recording): int {
        // Warm the cached read models first, so the figure is the steady-state
        // cost of the page rather than a one-off rebuild.
        $this->get(route('catalog'))->assertOk();

        $queries = 0;
        $recording = true;

        $this->get(route('catalog'))->assertOk();

        $recording = false;

        return $queries;
    };

    Product::factory()->published()->create();

    $single = $measure();

    // Enough to fill a page. Creating products invalidates the counts cache,
    // which the warm-up inside $measure rebuilds before anything is counted.
    Product::factory()->count(29)->published()->create();

    expect($measure())->toBe($single);
});

test('the category membership map is read once and served from cache', function () {
    $category = Category::factory()->create();
    $products = Product::factory()->count(3)->published()->create(['primary_category_id' => $category->id]);

    $this->get(route('catalog'))->assertOk();

    // Product ids rather than a tally, so a subtree rollup can de-duplicate a
    // product filed in two of its categories.
    $cached = Cache::get(StorefrontCache::CATEGORY_PRODUCT_IDS);

    expect(array_keys($cached))->toBe([$category->id]);
    expect(collect($cached[$category->id])->sort()->values()->all())
        ->toBe($products->pluck('id')->sort()->values()->all());

    // A second render reads the cached map rather than re-scanning the catalog.
    $scans = 0;
    DB::listen(function ($query) use (&$scans): void {
        if (stripos($query->sql, 'membership') !== false) {
            $scans++;
        }
    });

    $this->get(route('catalog'))->assertOk();

    expect($scans)->toBe(0);
});

test('a typed wildcard matches itself instead of matching everything', function () {
    // The suite runs on SQLite and production on MySQL, and the two disagree
    // about a backslash escape, so this pins the behaviour the ESCAPE clause
    // exists to make identical on both.
    $literal = Product::factory()->published()->create(['name' => '100% Cotton Sheet']);
    Product::factory()->published()->create(['name' => 'Denim Jacket']);
    Product::factory()->published()->create(['name' => 'Axb Adapter']);
    Product::factory()->published()->create(['name' => 'A_b Adapter']);

    // A bare wildcard must not open the catalog.
    $this->get(route('catalog', ['q' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $literal->id));

    // And a term containing one must find the product that really contains it.
    $this->get(route('catalog', ['q' => '100%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $literal->id));

    // `_` is a single-character wildcard; typed, it must match a literal one.
    $this->get(route('catalog', ['q' => 'a_b']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)
            ->where('products.data.0.name', 'A_b Adapter'));
});

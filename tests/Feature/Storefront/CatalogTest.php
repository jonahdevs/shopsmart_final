<?php

use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
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

    // Eleven at the time of writing: the paginator count, the page itself, the
    // brand and media eager loads, the two facet aggregates and their lookups,
    // and the store settings. The number must not grow with the page size —
    // anything that does is an N+1.
    expect($queries)->toBeLessThanOrEqual(12);
});

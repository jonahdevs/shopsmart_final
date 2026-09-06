<?php

use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The full search results page.
 *
 * It is the catalog listing with a term on it, so the filter engine itself is
 * already covered by CatalogTest. What is tested here is everything the search
 * page decides for itself: whether a request is a search at all, which
 * visibility rule the results obey, and whether the facets beside them describe
 * the term rather than the whole shop.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('the search page renders matching products', function () {
    $match = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);
    Product::factory()->published()->create(['name' => 'Toaster Grande']);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Search')
            ->where('searched', true)
            ->where('filters.q', 'kettle')
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id));
});

test('the search page asks for a term rather than rejecting a request without one', function () {
    Product::factory()->published()->create();

    $this->get(route('search'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Search')
            ->where('searched', false)
            ->where('filters.q', '')
            ->has('products.data', 0)
            ->has('categoryFacets', 0)
            ->has('brandFacets', 0));
});

test('the search page treats a one character term as not yet a search', function () {
    Product::factory()->published()->create(['name' => 'Kettle Deluxe']);

    // Deliberately not the 422 the suggest endpoint answers with: a shopper can
    // submit the header box mid-word, and bouncing a navigation for it would
    // take them somewhere they did not ask to go.
    $this->get(route('search', ['q' => 'k']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('searched', false)
            ->where('filters.q', 'k')
            ->has('products.data', 0));
});

test('the search page reports a term that matches nothing', function () {
    Product::factory()->published()->create(['name' => 'Kettle Deluxe']);

    $this->get(route('search', ['q' => 'harpsichord']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('searched', true)
            ->has('products.data', 0)
            ->where('products.total', 0));
});

test('a draft, archived, scheduled or hidden product never reaches the results', function () {
    $live = Product::factory()->published()->create(['name' => 'Kettle Live']);

    Product::factory()->draft()->create(['name' => 'Kettle Draft']);
    Product::factory()->archived()->create(['name' => 'Kettle Archived']);
    Product::factory()->scheduled(now()->addDay())->create(['name' => 'Kettle Scheduled']);
    Product::factory()->published()->hidden()->create(['name' => 'Kettle Hidden']);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $live->id));
});

test('the results obey search visibility, not catalog visibility', function () {
    // The distinction the two flags exist for: a search-only product has a real
    // product page and belongs here, while a catalog-only one would be a tile
    // whose page answers 404.
    $searchOnly = Product::factory()->published()->create([
        'name' => 'Kettle Search Only',
        'visibility' => ProductVisibility::Search,
    ]);
    Product::factory()->published()->create([
        'name' => 'Kettle Catalog Only',
        'visibility' => ProductVisibility::Catalog,
    ]);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $searchOnly->id));
});

test('the search term matches the name, sku, model number, short description and brand', function () {
    $byName = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);
    $bySku = Product::factory()->published()->create(['name' => 'Boiler', 'sku' => 'KETTLE-99']);
    $byModel = Product::factory()->published()->create(['name' => 'Urn', 'model_number' => 'KETTLE-X1']);
    $byDescription = Product::factory()->published()->create([
        'name' => 'Samovar',
        'short_description' => 'A stovetop kettle for large households.',
    ]);

    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    $byBrand = Product::factory()->published()->create(['name' => 'Percolator', 'brand_id' => $brand->id]);

    Product::factory()->published()->create(['name' => 'Toaster']);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 5)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === collect([$byName->id, $bySku->id, $byModel->id, $byDescription->id, $byBrand->id])->sort()->values()->all()));
});

test('a typed wildcard is searched literally rather than matching the whole catalog', function () {
    // Escaped through containsPattern(); Scout's database engine interpolates
    // the raw term instead, which is exactly why the term does not go through it.
    $literal = Product::factory()->published()->create(['name' => '100% Cotton Sheet']);
    Product::factory()->count(3)->published()->create();
    Product::factory()->published()->create(['name' => 'Axb Adapter']);
    Product::factory()->published()->create(['name' => 'A_b Adapter']);

    $this->get(route('search', ['q' => '%%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));

    $this->get(route('search', ['q' => '100%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $literal->id));

    $this->get(route('search', ['q' => 'a_b']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'A_b Adapter'));
});

test('the facets count the term, not the whole shop, and ticking one returns that number', function () {
    $wanted = Category::factory()->create(['name' => 'Kitchen']);
    $other = Category::factory()->create(['name' => 'Garden']);

    $inWanted = Product::factory()->count(2)->published()->create([
        'name' => 'Kettle Deluxe',
        'primary_category_id' => $wanted->id,
    ]);

    // Matches the term but sits elsewhere, and one that does not match at all.
    Product::factory()->published()->create(['name' => 'Kettle Garden', 'primary_category_id' => $other->id]);
    Product::factory()->count(4)->published()->create(['primary_category_id' => $wanted->id]);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 3)
            ->has('categoryFacets', 2)
            ->where('categoryFacets.0.slug', $other->slug)
            ->where('categoryFacets.0.count', 1)
            ->where('categoryFacets.1.slug', $wanted->slug)
            ->where('categoryFacets.1.count', 2));

    $this->get(route('search', ['q' => 'kettle', 'cat' => [$wanted->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 2)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === $inWanted->pluck('id')->sort()->values()->all()));
});

test('a brand facet narrows the results to that brand', function () {
    $wanted = Brand::factory()->create(['name' => 'Alpha']);
    $other = Brand::factory()->create(['name' => 'Beta']);

    $match = Product::factory()->published()->create(['name' => 'Kettle One', 'brand_id' => $wanted->id]);
    Product::factory()->published()->create(['name' => 'Kettle Two', 'brand_id' => $other->id]);

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('brandFacets', 2)
            ->where('brandFacets.0.id', $wanted->id)
            ->where('brandFacets.0.count', 1));

    $this->get(route('search', ['q' => 'kettle', 'brand' => [$wanted->id]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id));
});

test('the search page sorts by effective price like the catalog does', function () {
    $dear = Product::factory()->published()->create(['name' => 'Kettle Dear', 'price' => 900_000]);
    $discounted = Product::factory()->published()->create([
        'name' => 'Kettle Cheap',
        'price' => 800_000,
        'sale_price' => 100_000,
    ]);

    $this->get(route('search', ['q' => 'kettle', 'sort' => 'price-asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.id', $discounted->id)
            ->where('products.data.1.id', $dear->id));
});

test('page two of a search keeps the term and returns the remainder', function () {
    Product::factory()->count(30)->published()->create(['name' => 'Kettle Deluxe']);
    Product::factory()->count(5)->published()->create(['name' => 'Toaster']);

    $this->get(route('search', ['q' => 'kettle', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 6)
            ->where('products.currentPage', 2)
            ->where('products.total', 30)
            ->where('products.hasMorePages', false)
            ->where('filters.q', 'kettle'));
});

test('the search page issues a bounded number of queries for a page of results', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    Product::factory()
        ->count(30)
        ->published()
        ->create([
            'name' => 'Kettle Deluxe',
            'primary_category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

    // Warm the settings and tree reads so the figure is the steady-state cost.
    $this->get(route('search', ['q' => 'kettle']))->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 24));

    // A coarse tripwire for a page that has grown a whole new dependency. The
    // N+1 guard proper is the test below.
    expect($queries)->toBeLessThanOrEqual(14);
});

test('the search page costs the same number of queries whatever the page holds', function () {
    $queries = 0;
    $recording = false;

    DB::listen(function () use (&$queries, &$recording): void {
        if ($recording) {
            $queries++;
        }
    });

    $measure = function () use (&$queries, &$recording): int {
        $this->get(route('search', ['q' => 'kettle']))->assertOk();

        $queries = 0;
        $recording = true;

        $this->get(route('search', ['q' => 'kettle']))->assertOk();

        $recording = false;

        return $queries;
    };

    Product::factory()->published()->create(['name' => 'Kettle Deluxe']);

    $single = $measure();

    Product::factory()->count(29)->published()->create(['name' => 'Kettle Deluxe']);

    expect($measure())->toBe($single);
});

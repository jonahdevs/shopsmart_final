<?php

use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The header autocomplete. It runs on every keystroke, so the two-character
 * floor and the visibility rules matter more here than the ranking does.
 */
test('search suggest needs at least two characters', function () {
    $this->getJson(route('search.suggest', ['q' => 'k']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');

    $this->getJson(route('search.suggest'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search suggest returns matching products, categories and brands', function () {
    $product = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);
    Product::factory()->published()->create(['name' => 'Toaster']);

    $category = Category::factory()->create(['name' => 'Kettles']);
    Category::factory()->create(['name' => 'Toasters']);

    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    Product::factory()->published()->create(['brand_id' => $brand->id]);
    Brand::factory()->create(['name' => 'Toastworks']);

    $response = $this->getJson(route('search.suggest', ['q' => 'kettle']))->assertOk();

    expect($response->json('query'))->toBe('kettle')
        ->and($response->json('products.*.id'))->toContain($product->id)
        ->and($response->json('categories.*.id'))->toBe([$category->id])
        ->and($response->json('brands.*.id'))->toBe([$brand->id]);
});

test('search suggest hides products that are not published or not search visible', function () {
    Product::factory()->draft()->create(['name' => 'Kettle Draft']);
    Product::factory()->published()->hidden()->create(['name' => 'Kettle Hidden']);
    Product::factory()->published()->create(['name' => 'Kettle Catalog', 'visibility' => ProductVisibility::Catalog]);
    $visible = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);

    $response = $this->getJson(route('search.suggest', ['q' => 'kettle']))->assertOk();

    expect($response->json('products.*.id'))->toBe([$visible->id]);
});

test('search suggest does not aggregate the whole catalog to answer a keystroke', function () {
    $category = Category::factory()->create(['name' => 'Kettles']);
    $brand = Brand::factory()->create(['name' => 'Kettleworks']);

    Product::factory()
        ->count(30)
        ->published()
        ->create(['primary_category_id' => $category->id, 'brand_id' => $brand->id]);

    $queries = 0;
    $sql = [];
    DB::listen(function ($query) use (&$queries, &$sql): void {
        $queries++;
        $sql[] = $query->sql;
    });

    $response = $this->getJson(route('search.suggest', ['q' => 'kettle']))->assertOk();

    // Assert the endpoint did the work before budgeting it. This test used to
    // allow five queries, and passed — because the products are named by the
    // factory and only their BRAND is called Kettleworks, so the Scout database
    // engine (which only knows real columns on `products`) matched nothing and
    // the endpoint was cheap by being empty. A budget that a broken endpoint
    // satisfies is not a budget.
    expect($response->json('products'))->not->toBeEmpty();

    // Eight: nav categories, the inventory settings read behind
    // honorStockVisibility(), the product search, its brand and media eager
    // loads, the currency settings read behind money(), then the category and
    // brand lookups.
    expect($queries)->toBeLessThanOrEqual(8);

    // The count that actually matters, stated directly rather than as a number:
    // decorating category rows with live product counts pulls in a store-wide
    // aggregate that scans the whole catalog, on an endpoint firing on every
    // keystroke. No query here may group over products.
    expect($sql)->each->not->toContain('group by');
});

test('a typed wildcard is matched literally instead of returning the whole shop', function () {
    // Scout's database engine builds its pattern as '%'.$term.'%' with no
    // wildcard escaping, so routing this endpoint through Scout meant a shopper
    // typing a bare `%` was handed arbitrary products. The dropdown now matches
    // the way the results page does, through the catalog's escaped LIKE.
    Product::factory()->published()->create(['name' => 'Plain Kettle']);
    Product::factory()->published()->create(['name' => '100% Wool Throw']);

    $response = $this->getJson(route('search.suggest', ['q' => '100%']))->assertOk();

    expect($response->json('products'))->toHaveCount(1)
        ->and($response->json('products.0.name'))->toBe('100% Wool Throw');

    $bare = $this->getJson(route('search.suggest', ['q' => '%%']))->assertOk();

    expect($bare->json('products'))->toHaveCount(0);
});

test('the dropdown offers the same products the results page then shows', function () {
    // The two used to match different ways and could disagree, so a shopper
    // could click a row in the dropdown and not find it on the page behind it.
    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    Product::factory()->published()->create([
        'name' => 'Unrelated Name',
        'brand_id' => $brand->id,
    ]);
    Product::factory()->published()->create([
        'name' => 'Something Else',
        'short_description' => 'A kettleworks accessory.',
    ]);
    Product::factory()->published()->create(['name' => 'Toaster']);

    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $suggested = $this->getJson(route('search.suggest', ['q' => 'kettleworks']))
        ->assertOk()
        ->json('products.*.id');

    $onPage = [];

    $this->get(route('search', ['q' => 'kettleworks']))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$onPage): void {
            $onPage = array_column($page->toArray()['props']['products']['data'], 'id');
        });

    // Both must find the product matched only by its brand name and the one
    // matched only in its short description — neither is reachable by name.
    expect($suggested)->toHaveCount(2)
        ->and($onPage)->toEqualCanonicalizing($suggested);
});

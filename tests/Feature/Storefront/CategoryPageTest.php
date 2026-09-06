<?php

use App\Enums\CategoryStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The category listing runs the same engine as the catalog, so these tests
 * cover only what is specific to it: subtree scoping, the breadcrumb trail,
 * child facets, and the guarantee that a hand-edited `cat[]` cannot widen the
 * listing past the category the shopper is on.
 */
test('a category listing rolls up its whole subtree', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->child($parent)->create();
    $grandchild = Category::factory()->child($child)->create();
    $unrelated = Category::factory()->create();

    $inParent = Product::factory()->published()->create(['primary_category_id' => $parent->id]);
    $inChild = Product::factory()->published()->create(['primary_category_id' => $child->id]);
    $inGrandchild = Product::factory()->published()->create(['primary_category_id' => $grandchild->id]);
    Product::factory()->published()->create(['primary_category_id' => $unrelated->id]);

    $this->get(route('category.show', $parent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Category')
            ->where('category.id', $parent->id)
            ->has('products.data', 3)
            ->where('products.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all()
                === collect([$inParent->id, $inChild->id, $inGrandchild->id])->sort()->values()->all()));
});

test('a category listing narrows to a selected child subtree', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->child($parent)->create();
    $grandchild = Category::factory()->child($child)->create();
    $sibling = Category::factory()->child($parent)->create();

    $inGrandchild = Product::factory()->published()->create(['primary_category_id' => $grandchild->id]);
    Product::factory()->published()->create(['primary_category_id' => $sibling->id]);

    $this->get(route('category.show', ['category' => $parent, 'cat' => [$child->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $inGrandchild->id));
});

test('a tampered cat parameter cannot escape the category subtree', function () {
    $category = Category::factory()->create();
    $outsider = Category::factory()->create();

    Product::factory()->published()->create(['primary_category_id' => $category->id]);
    Product::factory()->published()->create(['primary_category_id' => $outsider->id]);

    $this->get(route('category.show', ['category' => $category, 'cat' => [$outsider->slug]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
});

test('a category listing carries a breadcrumb trail down from the root', function () {
    $root = Category::factory()->create(['name' => 'Home & Living']);
    $child = Category::factory()->child($root)->create(['name' => 'Kitchen']);

    $this->get(route('category.show', $child))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('breadcrumbs', 4)
            ->where('breadcrumbs.2.name', 'Home & Living')
            ->where('breadcrumbs.3.name', 'Kitchen')
            ->where('breadcrumbs.3.slug', $child->slug));
});

test('category facets offer only children whose subtree holds products', function () {
    $parent = Category::factory()->create();
    $stocked = Category::factory()->child($parent)->create(['name' => 'Stocked']);
    $grandchild = Category::factory()->child($stocked)->create();
    Category::factory()->child($parent)->create(['name' => 'Empty']);

    Product::factory()->published()->create(['primary_category_id' => $grandchild->id]);

    $this->get(route('category.show', $parent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categoryFacets', 1)
            ->where('categoryFacets.0.slug', $stocked->slug)
            ->where('categoryFacets.0.count', 1));
});

test('brand facets on a category page only count brands inside the subtree', function () {
    $category = Category::factory()->create();
    $inside = Brand::factory()->create(['name' => 'Inside']);
    $outside = Brand::factory()->create(['name' => 'Outside']);

    Product::factory()->published()->create([
        'primary_category_id' => $category->id,
        'brand_id' => $inside->id,
    ]);
    Product::factory()->published()->create(['brand_id' => $outside->id]);

    $this->get(route('category.show', $category))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('brandFacets', 1)
            ->where('brandFacets.0.id', $inside->id));
});

test('a category that is not active is not reachable', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Draft]);

    $this->get(route('category.show', $category))->assertNotFound();
});

test('the categories index lists active categories with their product counts', function () {
    $active = Category::factory()->create(['name' => 'Active', 'sort_order' => 1]);
    $child = Category::factory()->child($active)->create(['name' => 'Child', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'Archived', 'sort_order' => 3, 'status' => CategoryStatus::Archived]);

    Product::factory()->published()->create(['primary_category_id' => $active->id]);
    Product::factory()->published()->create(['primary_category_id' => $child->id]);

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Categories')
            // Roots only. The child is present once, nested under its parent —
            // sending it at the top level as well put it in the payload twice.
            ->has('categories', 1)
            ->where('categories.0.name', 'Active')
            // Rolled up over the subtree: one product of its own plus the
            // child's. The category page lists 2 when this is clicked, so
            // reporting 1 here was a number the shopper could catch us on.
            ->where('categories.0.productCount', 2)
            ->has('categories.0.children', 1)
            ->where('categories.0.children.0.productCount', 1));
});

test('an active category under an inactive parent is still listed as a root', function () {
    // Otherwise it would vanish from the index while remaining reachable at its
    // own URL, because the parent that would have nested it is filtered out.
    $drafted = Category::factory()->create([
        'name' => 'Drafted parent',
        'status' => CategoryStatus::Draft,
    ]);
    Category::factory()->child($drafted)->create(['name' => 'Orphaned child']);

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Orphaned child'));
});

test('a child tile carries the copy and cover art the grid renders', function () {
    $parent = Category::factory()->create();
    Category::factory()->child($parent)->create([
        'name' => 'Kettles',
        'description' => 'Everything that boils.',
    ]);

    $this->get(route('category.show', $parent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('category.children', 1)
            ->where('category.children.0.name', 'Kettles')
            // A narrowed column list on the child query handed the tile a null
            // description and icon on every render.
            ->where('category.children.0.description', 'Everything that boils.'));
});

test('a product filed in both a parent and its child is counted once in the subtree facet', function () {
    $root = Category::factory()->create();
    $parent = Category::factory()->child($root)->create(['name' => 'Kitchen']);
    $child = Category::factory()->child($parent)->create();

    $product = Product::factory()->published()->create(['primary_category_id' => $parent->id]);
    $product->categories()->attach($child);

    $this->get(route('category.show', $root))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // Summing per-category tallies said two; the grid returns one.
            ->has('products.data', 1)
            ->where('category.productCount', 1)
            ->has('categoryFacets', 1)
            ->where('categoryFacets.0.slug', $parent->slug)
            ->where('categoryFacets.0.count', 1));
});

test('a category page issues a bounded number of queries for a page of products', function () {
    $parent = Category::factory()->create();
    // Several children, not one: a media read per child tile is an N+1 that a
    // single-child fixture cannot tell apart from a single eager load.
    $children = Category::factory()->count(6)->child($parent)->create();
    $brand = Brand::factory()->create();

    Product::factory()
        ->count(30)
        ->published()
        ->create(['primary_category_id' => $children->first()->id, 'brand_id' => $brand->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('category.show', $parent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 24)
            ->has('category.children', 6));

    // The category tree is read once and walked in memory for all three of the
    // subtree scope, the child counts and the breadcrumbs. Anything that grows
    // this with the page size or the number of children is an N+1; anything
    // that adds a flat query is probably a second read of a list already in
    // hand.
    //
    // Sixteen rather than fifteen since the footer's social links joined the
    // shared props: one settings read, on a cold cache, once an hour in
    // production. Eighteen since the consent gate joined the document head,
    // which reads the legal and analytics groups on the same terms. Twenty
    // since the SEO head joined it too, reading SeoSettings and
    // BrandingSettings for the title pattern, description fallback and robots
    // directive — cached together under StorefrontCache::SEO.
    expect($queries)->toBeLessThanOrEqual(20);
});

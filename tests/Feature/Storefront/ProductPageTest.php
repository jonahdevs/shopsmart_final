<?php

use App\Enums\ProductLinkType;
use App\Enums\ProductVisibility;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductLink;
use App\Models\ProductVariant;
use App\Models\ProductView;
use App\Models\RecentlyViewed;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * Curate a typed link from one product to another, the way the admin side does.
 */
function curateProductLink(Product $product, Product $linked, ProductLinkType $type, int $sortOrder = 0): ProductLink
{
    return ProductLink::create([
        'product_id' => $product->id,
        'linked_product_id' => $linked->id,
        'type' => $type,
        'sort_order' => $sortOrder,
    ]);
}

/**
 * The product detail page: the payload it hands the client, who is allowed to
 * see it, the views it records, and the recommendation rails hanging off it.
 */
test('a product page renders the full detail payload', function () {
    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    $parent = Category::factory()->create(['name' => 'Home']);
    $category = Category::factory()->child($parent)->create(['name' => 'Kitchen']);

    $product = Product::factory()->published()->create([
        'name' => 'Kettle Deluxe',
        'brand_id' => $brand->id,
        'primary_category_id' => $category->id,
        'price' => 800_000,
        'sale_price' => 600_000,
    ]);

    Review::factory()->approved()->for($product)->create(['rating' => 4]);

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Product')
            ->where('product.id', $product->id)
            ->where('product.brand.name', 'Kettleworks')
            ->where('product.primaryCategory.name', 'Kitchen')
            ->where('product.priceFormatted', 'KES 8,000')
            ->where('product.salePriceFormatted', 'KES 6,000')
            ->where('product.effectivePriceFormatted', 'KES 6,000')
            ->where('product.discountPercent', 25)
            ->where('product.isOnSale', true)
            ->where('product.ratingCount', 1)
            ->where('product.ratingAverage', fn ($average) => (float) $average === 4.0)
            ->where('product.requiresOptions', false)
            ->has('product.breadcrumbs', 5)
            ->where('product.breadcrumbs.2.name', 'Home')
            ->where('product.breadcrumbs.3.name', 'Kitchen')
            ->where('product.breadcrumbs.4.name', 'Kettle Deluxe'));
});

test('a product page exposes variants and the axes they vary along', function () {
    $size = Attribute::factory()->create(['name' => 'Size', 'sort_order' => 0]);
    $small = AttributeValue::factory()->forAttribute($size)->create(['label' => 'Small', 'sort_order' => 0]);
    $large = AttributeValue::factory()->forAttribute($size)->create(['label' => 'Large', 'sort_order' => 1]);

    $product = Product::factory()->published()->variable()->create();

    $smallVariant = ProductVariant::factory()->for($product)->create(['price' => 100_000, 'sort_order' => 0]);
    $smallVariant->attributeValues()->attach($small);

    $largeVariant = ProductVariant::factory()->for($product)->create(['price' => 200_000, 'sort_order' => 1]);
    $largeVariant->attributeValues()->attach($large);

    ProductVariant::factory()->for($product)->inactive()->create();

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.isVariable', true)
            ->where('product.requiresOptions', true)
            ->has('product.variants', 2)
            ->where('product.variants.0.id', $smallVariant->id)
            ->where('product.variants.0.optionLabel', 'Small')
            ->where('product.variants.0.attributeValueIds.0', $small->id)
            ->where('product.variants.1.effectivePriceFormatted', 'KES 2,000')
            ->has('product.variationAttributes', 1)
            ->where('product.variationAttributes.0.name', 'Size')
            ->has('product.variationAttributes.0.values', 2));
});

test('a product page renders visible specification rows with human labels', function () {
    $material = Attribute::factory()->create(['name' => 'Material']);
    $steel = AttributeValue::factory()->forAttribute($material)->create(['slug' => 'steel', 'label' => 'Stainless Steel']);

    $product = Product::factory()->published()->create();

    ProductAttribute::create([
        'product_id' => $product->id,
        'attribute_id' => $material->id,
        'values' => [$steel->slug],
        'is_variation_attribute' => false,
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    ProductAttribute::create([
        'product_id' => $product->id,
        'attribute_id' => Attribute::factory()->create(['name' => 'Internal'])->id,
        'values' => ['whatever'],
        'is_variation_attribute' => false,
        'is_visible' => false,
        'sort_order' => 1,
    ]);

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('product.specifications', 1)
            ->where('product.specifications.0.name', 'Material')
            ->where('product.specifications.0.values.0', 'Stainless Steel'));
});

test('a product that is not live is not reachable', function () {
    $this->get(route('product.show', Product::factory()->draft()->create()))->assertNotFound();
    $this->get(route('product.show', Product::factory()->archived()->create()))->assertNotFound();
    $this->get(route('product.show', Product::factory()->scheduled(now()->addDay())->create()))->assertNotFound();
});

test('a hidden product is not reachable but a catalog only product is', function () {
    $this->get(route('product.show', Product::factory()->published()->hidden()->create()))->assertNotFound();

    $catalogOnly = Product::factory()->published()->create(['visibility' => ProductVisibility::Catalog]);

    $this->get(route('product.show', $catalogOnly))->assertOk();
});

test('viewing a product records it for analytics and for the signed in shopper', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create();

    $this->actingAs($user)->get(route('product.show', $product))->assertOk();

    expect(ProductView::where('product_id', $product->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(RecentlyViewed::where('product_id', $product->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('a product page recommends products from the same category and brand', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $product = Product::factory()->published()->create([
        'primary_category_id' => $category->id,
        'brand_id' => $brand->id,
    ]);

    $sameCategory = Product::factory()->published()->create(['primary_category_id' => $category->id]);
    $sameBrand = Product::factory()->published()->create(['brand_id' => $brand->id]);
    Product::factory()->published()->create();

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('related', 1)
            ->where('related.0.id', $sameCategory->id)
            ->has('brandProducts', 1)
            ->where('brandProducts.0.id', $sameBrand->id)
            ->has('alsoViewed', 0));
});

test('a product page recommends what was viewed in the same session', function () {
    $product = Product::factory()->published()->create();
    $alsoViewed = Product::factory()->published()->create();

    ProductView::insert([
        ['product_id' => $product->id, 'user_id' => null, 'session_id' => 'session-a', 'viewed_at' => now()],
        ['product_id' => $alsoViewed->id, 'user_id' => null, 'session_id' => 'session-a', 'viewed_at' => now()],
    ]);

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alsoViewed', 1)
            ->where('alsoViewed.0.id', $alsoViewed->id));
});

test('a product page offers its curated accessories in the order they were curated', function () {
    $product = Product::factory()->published()->create();

    $second = Product::factory()->published()->create(['name' => 'Descaler']);
    $first = Product::factory()->published()->create(['name' => 'Filter cartridge']);
    $crossSell = Product::factory()->published()->create();

    curateProductLink($product, $second, ProductLinkType::Accessory, sortOrder: 1);
    curateProductLink($product, $first, ProductLinkType::Accessory, sortOrder: 0);
    // Another product's accessory, and a link of a different type on this one:
    // neither belongs in this product's prompt.
    curateProductLink($product, $crossSell, ProductLinkType::CrossSell);
    curateProductLink($crossSell, Product::factory()->published()->create(), ProductLinkType::Accessory);

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('accessories', 2)
            ->where('accessories.0.name', 'Filter cartridge')
            ->where('accessories.1.name', 'Descaler')
            ->where('accessories.0.effectivePriceFormatted', fn ($price) => is_string($price)));
});

test('a product with nothing curated against it offers no accessories', function () {
    $product = Product::factory()->published()->create();
    Product::factory()->published()->create();

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('accessories', 0));
});

test('an accessory a shopper could not buy is never offered', function () {
    $product = Product::factory()->published()->create();

    $offered = Product::factory()->published()->create(['name' => 'Filter cartridge']);

    $unbuyable = [
        Product::factory()->draft()->create(),
        Product::factory()->published()->hidden()->create(),
        Product::factory()->published()->outOfStock()->create(),
        Product::factory()->published()->withoutPrice()->create(),
        Product::factory()->scheduled(now()->addDay())->create(),
    ];

    foreach ([$offered, ...$unbuyable] as $index => $accessory) {
        curateProductLink($product, $accessory, ProductLinkType::Accessory, sortOrder: $index);
    }

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('accessories', 1)
            ->where('accessories.0.id', $offered->id));
});

test('a product page issues a bounded number of queries however much hangs off it', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $product = Product::factory()->published()->create([
        'primary_category_id' => $category->id,
        'brand_id' => $brand->id,
    ]);

    Product::factory()->published()->count(5)->create([
        'primary_category_id' => $category->id,
        'brand_id' => $brand->id,
    ])->each(fn (Product $accessory) => curateProductLink($product, $accessory, ProductLinkType::Accessory));

    Review::factory()->approved()->count(3)->for($product)->create();

    // The recommendation pools are cached per product, so the run being
    // measured must be the cold one that actually resolves them.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('product.show', $product))->assertOk();

    // 23 with nothing curated against the product, 26 with accessories: the
    // prompt costs one join over the link table plus the brand and media eager
    // loads every other product tile on this page already pays for. Flat in the
    // number of accessories — five are linked here and four are offered.
    //
    // The 27th is the footer's social links joining the shared props: one
    // settings read, on a cold cache, once an hour in production. The 28th and
    // 29th are the consent gate in the document head, which reads the legal and
    // analytics groups on the same terms — see App\Support\PrivacyConfig.
    expect($queries)->toBeLessThanOrEqual(29);
});

test('a product page defers its reviews', function () {
    $product = Product::factory()->published()->create();
    Review::factory()->approved()->for($product)->create();
    Review::factory()->pending()->for($product)->create();

    $this->get(route('product.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('reviews')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload->has('reviews', 1)));
});

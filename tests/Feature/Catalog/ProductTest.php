<?php

use App\Enums\ProductLinkType;
use App\Enums\ProductVisibility;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Settings\InventorySettings;

test('published scope includes published and elapsed scheduled products', function () {
    $published = Product::factory()->published()->create();
    $live = Product::factory()->scheduled(now()->subDay())->create();
    $pending = Product::factory()->scheduled(now()->addDay())->create();
    $draft = Product::factory()->draft()->create();
    $archived = Product::factory()->archived()->create();

    $ids = Product::published()->pluck('id');

    expect($ids)->toContain($published->id, $live->id)
        ->not->toContain($pending->id)
        ->not->toContain($draft->id)
        ->not->toContain($archived->id);
});

test('visibility scopes separate catalog listings from search results', function () {
    $visible = Product::factory()->create(['visibility' => ProductVisibility::Visible]);
    $catalogOnly = Product::factory()->create(['visibility' => ProductVisibility::Catalog]);
    $searchOnly = Product::factory()->create(['visibility' => ProductVisibility::Search]);
    $hidden = Product::factory()->hidden()->create();

    expect(Product::visibleInCatalog()->pluck('id'))
        ->toContain($visible->id, $catalogOnly->id)
        ->not->toContain($searchOnly->id)
        ->not->toContain($hidden->id);

    expect(Product::visibleInSearch()->pluck('id'))
        ->toContain($visible->id, $searchOnly->id)
        ->not->toContain($catalogOnly->id)
        ->not->toContain($hidden->id);
});

test('honorStockVisibility hides out of stock products only when the store says to', function () {
    $inStock = Product::factory()->create();
    $outOfStock = Product::factory()->outOfStock()->create();

    InventorySettings::fake(['out_of_stock_behavior' => 'show']);
    expect(Product::honorStockVisibility()->pluck('id'))->toContain($outOfStock->id);

    InventorySettings::fake(['out_of_stock_behavior' => 'hide']);
    expect(Product::honorStockVisibility()->pluck('id'))
        ->toContain($inStock->id)
        ->not->toContain($outOfStock->id);
});

test('withReviewStats aggregates approved reviews only', function () {
    $product = Product::factory()->create();
    Review::factory()->approved()->for($product)->create(['rating' => 5]);
    Review::factory()->approved()->for($product)->create(['rating' => 3]);
    Review::factory()->pending()->for($product)->create(['rating' => 1]);

    $found = Product::withReviewStats()->find($product->id);

    expect($found->reviews_count)->toBe(2)
        ->and((float) $found->reviews_avg_rating)->toBe(4.0);
});

test('loadReviewStatsIfMissing does not overwrite aggregates the query already selected', function () {
    $product = Product::factory()->create();
    Review::factory()->approved()->for($product)->create(['rating' => 4]);

    $eager = Product::withReviewStats()->find($product->id);
    $eager->loadReviewStatsIfMissing();
    expect($eager->reviews_count)->toBe(1);

    $lazy = Product::find($product->id)->loadReviewStatsIfMissing();
    expect($lazy->reviews_count)->toBe(1);
});

test('pricing helpers report the effective price and discount', function () {
    $plain = Product::factory()->create(['price' => 100_000, 'sale_price' => null]);
    expect($plain->effectivePriceCents())->toBe(100_000)
        ->and($plain->isOnSale())->toBeFalse()
        ->and($plain->discountPercent())->toBeNull();

    $discounted = Product::factory()->create(['price' => 100_000, 'sale_price' => 75_000]);
    expect($discounted->effectivePriceCents())->toBe(75_000)
        ->and($discounted->isOnSale())->toBeTrue()
        ->and($discounted->discountPercent())->toBe(25);

    $onApplication = Product::factory()->withoutPrice()->create();
    expect($onApplication->effectivePriceCents())->toBeNull();
});

test('a variant falls back to its parent price and labels its option combination', function () {
    $product = Product::factory()->variable()->create(['price' => 80_000]);

    $inheriting = ProductVariant::factory()->for($product)->create(['price' => null]);
    expect($inheriting->effectivePriceCents())->toBe(80_000);

    $overriding = ProductVariant::factory()->for($product)->onSale()->create(['price' => 120_000]);
    expect($overriding->effectivePriceCents())->toBe(96_000)
        ->and($overriding->isOnSale())->toBeTrue();

    $red = AttributeValue::factory()->create(['label' => 'Red', 'sort_order' => 1]);
    $large = AttributeValue::factory()->create(['label' => 'Large', 'sort_order' => 2]);
    $overriding->attributeValues()->attach([$large->id, $red->id]);

    expect($overriding->load('attributeValues')->optionLabel())->toBe('Red / Large');
});

test('typed link relations only return products linked with that type', function () {
    $product = Product::factory()->create();
    $accessory = Product::factory()->create();
    $sparePart = Product::factory()->create();

    ProductLink::create([
        'product_id' => $product->id,
        'linked_product_id' => $accessory->id,
        'type' => ProductLinkType::Accessory,
        'is_required' => true,
        'default_quantity' => 12,
    ]);
    ProductLink::create([
        'product_id' => $product->id,
        'linked_product_id' => $sparePart->id,
        'type' => ProductLinkType::SparePart,
    ]);

    expect($product->accessories->pluck('id')->all())->toBe([$accessory->id])
        ->and($product->spareParts->pluck('id')->all())->toBe([$sparePart->id])
        ->and($product->upsells)->toBeEmpty()
        ->and($product->accessories->first()->pivot->default_quantity)->toBe(12);
});

test('only live products are exposed to the search index', function () {
    expect(Product::factory()->published()->create()->shouldBeSearchable())->toBeTrue()
        ->and(Product::factory()->draft()->create()->shouldBeSearchable())->toBeFalse()
        ->and(Product::factory()->archived()->create()->shouldBeSearchable())->toBeFalse()
        ->and(Product::factory()->scheduled(now()->addDay())->create()->shouldBeSearchable())->toBeFalse();
});

test('a product resolves by slug for route model binding', function () {
    $product = Product::factory()->create(['slug' => 'commercial-oven-x40']);

    expect($product->getRouteKeyName())->toBe('slug')
        ->and(Product::where('slug', 'commercial-oven-x40')->first()->id)->toBe($product->id);
});

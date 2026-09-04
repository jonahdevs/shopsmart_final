<?php

use App\Enums\CategorySection;
use App\Enums\ProductType;
use App\Enums\ReviewStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryPlacement;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\Review;
use App\Models\TaxClass;
use App\Settings\TaxSettings;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\TagSeeder;
use Database\Seeders\TaxClassSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Guards the demo catalog these seeders build from database/data/*.json.
 *
 * The public disk is faked, so the seeders take their "referenced image is
 * missing" path and attach no media: this keeps the run fast and stops the
 * suite writing into storage/app/public. Seeding happens with model events
 * muted, exactly as DatabaseSeeder runs it in production.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->seedCatalog = fn (array $seeders = []) => Model::withoutEvents(fn () => $this->seed([
        TaxClassSeeder::class,
        BrandSeeder::class,
        CategorySeeder::class,
        AttributeSeeder::class,
        ProductSeeder::class,
        ...$seeders,
    ]));

    ($this->seedCatalog)();
});

test('the seeders build a populated catalog', function () {
    expect(Brand::count())->toBeGreaterThan(0)
        ->and(Category::count())->toBeGreaterThan(0)
        ->and(Product::count())->toBeGreaterThan(0)
        ->and(ProductLink::count())->toBeGreaterThan(0)
        ->and(Product::published()->visibleInCatalog()->count())->toBeGreaterThan(0);
});

test('the store default tax class points at a seeded, active band', function () {
    $defaultId = app(TaxSettings::class)->default_tax_class_id;

    expect($defaultId)->not->toBeNull()
        ->and(TaxClass::active()->whereKey($defaultId)->exists())->toBeTrue();
});

test('every category placement location is populated and ordered from one', function () {
    foreach (CategorySection::cases() as $location) {
        $placements = CategoryPlacement::forLocation($location)->get();

        expect($placements)->not->toBeEmpty()
            ->and($placements->pluck('sort_order')->all())
            ->toBe(range(1, $placements->count()));
    }
});

test('every variable product has variants with attribute values and a default', function () {
    $variable = Product::query()
        ->where('type', ProductType::Variable)
        ->with('variants.attributeValues')
        ->get();

    expect($variable)->not->toBeEmpty();

    foreach ($variable as $product) {
        expect($product->variants)->not->toBeEmpty()
            ->and($product->default_variant_id)->not->toBeNull();

        foreach ($product->variants as $variant) {
            expect($variant->attributeValues)->not->toBeEmpty();
        }
    }
});

test('every product with a primary category also sits in the category pivot', function () {
    $orphaned = Product::query()
        ->whereNotNull('primary_category_id')
        ->whereDoesntHave('categories', fn ($query) => $query->whereColumn('categories.id', 'products.primary_category_id'))
        ->count();

    expect($orphaned)->toBe(0);
});

test('every priced product is stored as a positive cents amount', function () {
    expect(Product::query()->whereNotNull('price')->where('price', '<=', 0)->count())->toBe(0)
        ->and(Product::query()->whereNotNull('sale_price')->where('sale_price', '<=', 0)->count())->toBe(0);

    $product = Product::query()->whereNotNull('price')->first();

    // Cents, not major units: the cheapest source price is over KES 100.
    expect($product->effectivePriceCents())->toBeGreaterThan(10_000);
});

test('the home page rails have tagged products to draw on', function () {
    Model::withoutEvents(fn () => $this->seed(TagSeeder::class));

    foreach (['Featured', 'New Arrival', 'Clearance'] as $tag) {
        expect(Product::withAnyTags([$tag])->count())->toBeGreaterThan(0);
    }
});

test('reviews are seeded both approved and awaiting moderation', function () {
    Model::withoutEvents(fn () => $this->seed(ReviewSeeder::class));

    expect(Review::query()->where('status', ReviewStatus::Approved)->count())->toBeGreaterThan(0)
        ->and(Review::query()->where('status', ReviewStatus::Pending)->count())->toBeGreaterThan(0);
});

test('re-running the seeders does not duplicate the catalog', function () {
    $before = [
        'products' => Product::count(),
        'variants' => Product::query()->where('type', ProductType::Variable)->withCount('variants')->get()->sum('variants_count'),
        'categories' => Category::count(),
        'links' => ProductLink::count(),
        'placements' => CategoryPlacement::count(),
    ];

    ($this->seedCatalog)();

    expect(Product::count())->toBe($before['products'])
        ->and(Product::query()->where('type', ProductType::Variable)->withCount('variants')->get()->sum('variants_count'))->toBe($before['variants'])
        ->and(Category::count())->toBe($before['categories'])
        ->and(ProductLink::count())->toBe($before['links'])
        ->and(CategoryPlacement::count())->toBe($before['placements']);
});

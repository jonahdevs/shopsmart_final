<?php

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * The products area of the admin panel.
 *
 * The decisions worth protecting here are the ones a screenshot cannot show:
 * that whole KES typed into the form become integer cents in the column, that
 * `products.view` and `products.manage` are genuinely different keys, that a
 * sort column off the whitelist never reaches `orderBy`, and that removing a
 * product takes it off the storefront without touching the orders that sold it.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The page components are built alongside these props; the assertions are
    // the contract between them.
    config()->set('inertia.testing.ensure_pages_exist', false);

    registerAdminCatalogRoutes();

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');

    // Support holds products.view and not products.manage — the exact split
    // the routes are grouped by.
    $this->support = User::factory()->create();
    $this->support->assignRole('Support');
});

/**
 * A complete, valid product payload. Money is in whole KES, as the form sends
 * it — never cents.
 *
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Countertop Dough Sheeter',
        'type' => 'simple',
        'status' => ProductStatus::Published->value,
        'visibility' => ProductVisibility::Visible->value,
        'price' => '1499',
        'is_taxable' => '1',
        'is_virtual' => '0',
        'requires_shipping' => '1',
        'stock_status' => StockStatus::InStock->value,
        'allow_backorder' => '0',
        ...$overrides,
    ];
}

// ==================================================
// WHO MAY DO WHAT
// ==================================================

test('a guest is sent to sign in rather than shown the products table', function () {
    $this->get(route('admin.products.index'))->assertRedirect(route('login'));
});

test('a customer is refused the products table', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.products.index'))
        ->assertForbidden();
});

test('a staff member without products.manage may read the table but not create', function () {
    $this->actingAs($this->support)
        ->get(route('admin.products.index'))
        ->assertOk();

    $this->actingAs($this->support)
        ->get(route('admin.products.create'))
        ->assertForbidden();

    $this->actingAs($this->support)
        ->post(route('admin.products.store'), productPayload())
        ->assertForbidden();

    expect(Product::query()->count())->toBe(0);
});

test('a staff member without products.manage cannot delete a product', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->support)
        ->delete(route('admin.products.destroy', $product))
        ->assertForbidden();

    expect($product->fresh()->trashed())->toBeFalse();
});

// ==================================================
// THE TABLE
// ==================================================

test('the table lists products with their brand, category and effective price', function () {
    $brand = Brand::factory()->create(['name' => 'Rondo']);
    $category = Category::factory()->create(['name' => 'Bakery']);

    $product = Product::factory()->create([
        'name' => 'Dough Divider',
        'brand_id' => $brand->id,
        'primary_category_id' => $category->id,
        'price' => 250_000,
        'sale_price' => 200_000,
    ]);

    ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/products/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Dough Divider')
            ->where('products.0.brandName', 'Rondo')
            ->where('products.0.categoryName', 'Bakery')
            // The sale price, because that is what the customer pays.
            ->where('products.0.priceCents', 200_000)
            ->where('products.0.isOnSale', true)
            ->where('products.0.variantCount', 2)
            ->where('pagination.total', 1));
});

test('the table filters by status, visibility, stock and brand', function () {
    $brand = Brand::factory()->create();

    $wanted = Product::factory()->published()->create([
        'brand_id' => $brand->id,
        'visibility' => ProductVisibility::Visible,
        'stock_status' => StockStatus::InStock,
    ]);

    Product::factory()->draft()->create(['brand_id' => $brand->id]);
    Product::factory()->published()->outOfStock()->create(['brand_id' => $brand->id]);
    Product::factory()->published()->create();

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', [
            'status' => ProductStatus::Published->value,
            'visibility' => ProductVisibility::Visible->value,
            'stock_status' => StockStatus::InStock->value,
            'brand' => $brand->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $wanted->id));
});

test('the category filter reads both the primary column and the pivot', function () {
    $category = Category::factory()->create();

    $byColumn = Product::factory()->create(['primary_category_id' => $category->id]);
    $byPivot = Product::factory()->create();
    $byPivot->categories()->attach($category);

    Product::factory()->create();

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['category' => $category->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 2)
            ->where('products', fn (Collection $rows): bool => $rows->pluck('id')->sort()->values()->all()
                === collect([$byColumn->id, $byPivot->id])->sort()->values()->all()));
});

test('a typed percent sign searches for itself rather than matching everything', function () {
    $literal = Product::factory()->create(['name' => 'Mixer 50% off bundle']);
    Product::factory()->create(['name' => 'Mixer standard bundle']);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $literal->id));
});

test('a typed underscore searches for itself rather than matching any character', function () {
    $literal = Product::factory()->create(['name' => 'Model A_1 grinder']);
    Product::factory()->create(['name' => 'Model AX1 grinder']);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['search' => 'A_1']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $literal->id));
});

test('a sort column off the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['sort' => 'deleted_at']))
        ->assertInvalid('sort');
});

test('sorting by price uses the effective price and puts unpriced products last', function () {
    $dear = Product::factory()->create(['name' => 'Dear', 'price' => 900_000]);
    // Cheaper than $dear only once its discount is taken into account.
    $discounted = Product::factory()->create(['name' => 'Discounted', 'price' => 1_000_000, 'sale_price' => 100_000]);
    $quoted = Product::factory()->withoutPrice()->create(['name' => 'On application']);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['sort' => 'price', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.id', $discounted->id)
            ->where('products.1.id', $dear->id)
            ->where('products.2.id', $quoted->id));
});

test('the table hides soft-deleted products unless asked for them', function () {
    $live = Product::factory()->create();
    $binned = Product::factory()->create();
    $binned->delete();

    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $live->id));

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['trashed' => 'only']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products', 1)
            ->where('products.0.id', $binned->id)
            ->where('products.0.isDeleted', true));
});

// ==================================================
// CREATING AND EDITING
// ==================================================

test('the create page opens on a blank product', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.products.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/products/Form')
            ->where('product.id', null)
            ->where('product.status', 'draft')
            ->has('categoryOptions')
            ->has('brandOptions')
            ->has('statusOptions'));
});

test('whole kes typed into the form are stored as integer cents', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload([
            'price' => '1499',
            'sale_price' => '1199.50',
            'cost_price' => '900',
        ]))
        ->assertSessionHasNoErrors();

    $product = Product::query()->sole();

    expect($product->price)->toBe(149900)
        ->and($product->sale_price)->toBe(119950)
        ->and($product->cost_price)->toBe(90000)
        ->and($product->effectivePriceCents())->toBe(119950);
});

test('creating a product without a slug derives a unique one from the name', function () {
    Product::factory()->create(['name' => 'Dough Sheeter', 'slug' => 'countertop-dough-sheeter']);

    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload())
        ->assertSessionHasNoErrors();

    expect(Product::query()->latest('id')->first()->slug)->toBe('countertop-dough-sheeter-2');
});

test('creating a product files it in its primary category as well as the pivot', function () {
    $primary = Category::factory()->create();
    $extra = Category::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload([
            'primary_category_id' => $primary->id,
            'categories' => [$extra->id],
        ]))
        ->assertSessionHasNoErrors();

    $product = Product::query()->sole();

    expect($product->categories->pluck('id')->sort()->values()->all())
        ->toBe(collect([$primary->id, $extra->id])->sort()->values()->all());
});

test('creating a product saves its tags', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload(['tags' => 'bakery, commercial ,bakery']))
        ->assertSessionHasNoErrors();

    expect(Product::query()->sole()->tags->pluck('name')->map(strval(...))->sort()->values()->all())
        ->toBe(['bakery', 'commercial']);
});

test('a sale price above the price it discounts is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload(['price' => '1000', 'sale_price' => '1200']))
        ->assertInvalid('sale_price');

    expect(Product::query()->count())->toBe(0);
});

test('a product without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload(['name' => '']))
        ->assertInvalid('name');
});

test('a slug already taken by another product is rejected', function () {
    Product::factory()->create(['slug' => 'dough-sheeter']);

    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload(['slug' => 'dough-sheeter']))
        ->assertInvalid('slug');
});

test('scheduling a product without a publish time is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.products.store'), productPayload([
            'status' => ProductStatus::Scheduled->value,
            'published_at' => '',
        ]))
        ->assertInvalid('published_at');
});

test('editing a product keeps its own slug free', function () {
    $product = Product::factory()->create(['slug' => 'dough-sheeter', 'name' => 'Dough Sheeter']);

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'slug' => 'dough-sheeter',
            'name' => 'Dough Sheeter Pro',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Dough Sheeter Pro');
});

test('the edit page sends prices back in whole kes, not cents', function () {
    $product = Product::factory()->create(['price' => 149900, 'sale_price' => 119950]);

    $this->actingAs($this->manager)
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/products/Form')
            ->where('product.price', 1499)
            ->where('product.salePrice', 1199.5));
});

// ==================================================
// VARIANTS
// ==================================================

test('saving a product creates, updates and removes its variants in one go', function () {
    $product = Product::factory()->variable()->create();
    $kept = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-KEEP', 'price' => 100_000]);
    $dropped = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-DROP']);

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'name' => $product->name,
            'type' => 'variable',
            'variants' => [
                [
                    'id' => $kept->id,
                    'sku' => 'VAR-KEEP',
                    'price' => '2000',
                    'sale_price' => '1500',
                    'stock_status' => StockStatus::InStock->value,
                    'allow_backorder' => '0',
                    'is_active' => '1',
                ],
                [
                    'sku' => 'VAR-NEW',
                    'price' => '2500',
                    'stock_status' => StockStatus::Backorder->value,
                    'allow_backorder' => '1',
                    'is_active' => '1',
                ],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $kept->refresh();

    expect($kept->price)->toBe(200000)
        // sale_price is what the customer pays, on a variant exactly as on a
        // product — the convention the reference app inverted.
        ->and($kept->sale_price)->toBe(150000)
        ->and($kept->effectivePriceCents())->toBe(150000)
        ->and($dropped->fresh()->trashed())->toBeTrue()
        ->and(ProductVariant::query()->where('sku', 'VAR-NEW')->sole()->price)->toBe(250000);
});

test('two variants claiming one sku are rejected', function () {
    $product = Product::factory()->variable()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'name' => $product->name,
            'type' => 'variable',
            'variants' => [
                ['sku' => 'VAR-SAME', 'stock_status' => StockStatus::InStock->value, 'allow_backorder' => '0', 'is_active' => '1'],
                ['sku' => 'VAR-SAME', 'stock_status' => StockStatus::InStock->value, 'allow_backorder' => '0', 'is_active' => '1'],
            ],
        ]))
        ->assertInvalid('variants.1.sku');

    expect(ProductVariant::query()->count())->toBe(0);
});

test('a variant sku another product already holds is rejected', function () {
    $product = Product::factory()->variable()->create();
    ProductVariant::factory()->create(['sku' => 'VAR-TAKEN']);

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'name' => $product->name,
            'type' => 'variable',
            'variants' => [
                ['sku' => 'VAR-TAKEN', 'stock_status' => StockStatus::InStock->value, 'allow_backorder' => '0', 'is_active' => '1'],
            ],
        ]))
        ->assertInvalid('variants.0.sku');
});

// ==================================================
// TYPED LINKS
// ==================================================

test('saving a product replaces its typed links', function () {
    $product = Product::factory()->create();
    $accessory = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'name' => $product->name,
            'links' => [
                [
                    'type' => 'accessory',
                    'linked_product_id' => $accessory->id,
                    'is_required' => '1',
                    'default_quantity' => '12',
                ],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $link = $product->links()->sole();

    expect($link->linked_product_id)->toBe($accessory->id)
        ->and($link->is_required)->toBeTrue()
        ->and($link->default_quantity)->toBe(12)
        ->and($product->fresh()->accessories)->toHaveCount(1);
});

test('a product cannot be linked to itself', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->patch(route('admin.products.update', $product), productPayload([
            'name' => $product->name,
            'links' => [
                ['type' => 'upsell', 'linked_product_id' => $product->id, 'is_required' => '0', 'default_quantity' => '1'],
            ],
        ]))
        ->assertInvalid('links.0.linked_product_id');
});

// ==================================================
// MEDIA
// ==================================================

test('images can be added to and removed from a product gallery', function () {
    Storage::fake(config('media-library.disk_name'));

    $product = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->post(route('admin.products.media.store', $product), [
            'images' => [UploadedFile::fake()->image('sheeter.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $media = $product->fresh()->getFirstMedia('images');

    expect($media)->not->toBeNull();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->delete(route('admin.products.media.destroy', [$product, $media]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->getMedia('images'))->toHaveCount(0);
});

test('an image belonging to another product cannot be deleted through this one', function () {
    Storage::fake(config('media-library.disk_name'));

    $owner = Product::factory()->create();
    $other = Product::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('admin.products.media.store', $owner), [
            'images' => [UploadedFile::fake()->image('sheeter.jpg')],
        ]);

    $media = $owner->fresh()->getFirstMedia('images');

    $this->actingAs($this->manager)
        ->delete(route('admin.products.media.destroy', [$other, $media]))
        ->assertNotFound();

    expect($owner->fresh()->getMedia('images'))->toHaveCount(1);
});

test('a file that is not an image is rejected', function () {
    Storage::fake(config('media-library.disk_name'));

    $product = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.edit', $product))
        ->post(route('admin.products.media.store', $product), [
            'images' => [UploadedFile::fake()->create('prices.pdf', 20, 'application/pdf')],
        ])
        ->assertInvalid('images.0');
});

// ==================================================
// REMOVING AND RESTORING
// ==================================================

test('deleting a product takes it off the storefront but leaves the orders that sold it', function () {
    $product = Product::factory()->published()->create(['name' => 'Dough Sheeter', 'sku' => 'DS-1']);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->forProduct($product)->create(['order_id' => $order->id]);

    $this->actingAs($this->manager)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->trashed())->toBeTrue();

    // Gone from the shop floor.
    $this->get(route('product.show', $product->slug))->assertNotFound();

    // The line that sold it still reads, name, SKU and price intact.
    $item->refresh();

    expect($item->exists)->toBeTrue()
        ->and($item->name)->toBe('Dough Sheeter')
        ->and($item->sku)->toBe('DS-1')
        ->and($item->unit_price_cents)->toBeGreaterThan(0);
});

test('a soft-deleted product can be restored', function () {
    $product = Product::factory()->published()->create();
    $product->delete();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'only']))
        ->patch(route('admin.products.restore', $product))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->trashed())->toBeFalse();
});

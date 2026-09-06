<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * Brands in the admin panel.
 *
 * The consequence worth protecting is what a delete does to the merchandise:
 * `products.brand_id` is nullOnDelete, so removing a brand unbrands its
 * products rather than removing them.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    registerAdminCatalogRoutes();

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');

    $this->support = User::factory()->create();
    $this->support->assignRole('Support');
});

/**
 * @return array<string, mixed>
 */
function brandPayload(array $overrides = []): array
{
    return [
        'name' => 'Rondo Doge',
        'is_active' => '1',
        ...$overrides,
    ];
}

test('a guest is sent to sign in rather than shown the brands', function () {
    $this->get(route('admin.brands.index'))->assertRedirect(route('login'));
});

test('a customer is refused the brands', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.brands.index'))
        ->assertForbidden();
});

test('a staff member without catalog.manage is refused the brands', function () {
    $this->actingAs($this->support)
        ->get(route('admin.brands.index'))
        ->assertForbidden();

    $this->actingAs($this->support)
        ->post(route('admin.brands.store'), brandPayload())
        ->assertForbidden();

    expect(Brand::query()->count())->toBe(0);
});

test('the table lists brands with the number of products behind each', function () {
    $brand = Brand::factory()->create(['name' => 'Rondo']);
    Product::factory()->count(3)->create(['brand_id' => $brand->id]);
    Brand::factory()->create(['name' => 'Sinmag']);

    $this->actingAs($this->manager)
        ->get(route('admin.brands.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/brands/Index')
            ->has('brands', 2)
            ->where('brands.0.name', 'Rondo')
            ->where('brands.0.productCount', 3)
            ->where('brands.1.productCount', 0));
});

test('a sort column off the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.brands.index', ['sort' => 'id']))
        ->assertInvalid('sort');
});

test('a typed percent sign searches for itself rather than matching everything', function () {
    $literal = Brand::factory()->create(['name' => 'Save 50% Co']);
    Brand::factory()->create(['name' => 'Savings Co']);

    $this->actingAs($this->manager)
        ->get(route('admin.brands.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('brands', 1)
            ->where('brands.0.id', $literal->id));
});

test('a brand can be created and slugged from its name', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.brands.store'), brandPayload())
        ->assertRedirect(route('admin.brands.index'));

    expect(Brand::query()->sole()->slug)->toBe('rondo-doge');
});

test('a brand without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.brands.store'), brandPayload(['name' => '']))
        ->assertInvalid('name');
});

test('a website that is not a url is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.brands.store'), brandPayload(['website_url' => 'not a url']))
        ->assertInvalid('website_url');
});

test('a slug another brand already holds is rejected', function () {
    Brand::factory()->create(['slug' => 'rondo-doge']);

    $this->actingAs($this->manager)
        ->post(route('admin.brands.store'), brandPayload(['slug' => 'rondo-doge']))
        ->assertInvalid('slug');
});

test('a brand can be renamed and deactivated', function () {
    $brand = Brand::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.brands.edit', $brand))
        ->patch(route('admin.brands.update', $brand), brandPayload([
            'name' => 'Rondo Kenya',
            'slug' => $brand->slug,
            'is_active' => '0',
        ]))
        ->assertSessionHasNoErrors();

    $brand->refresh();

    expect($brand->name)->toBe('Rondo Kenya')
        ->and($brand->is_active)->toBeFalse();
});

test('deleting a brand unbrands its products rather than deleting them', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $this->actingAs($this->manager)
        ->delete(route('admin.brands.destroy', $brand))
        ->assertRedirect(route('admin.brands.index'));

    $product->refresh();

    expect(Brand::query()->count())->toBe(0)
        ->and($product->exists)->toBeTrue()
        ->and($product->brand_id)->toBeNull();
});

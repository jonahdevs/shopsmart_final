<?php

use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use App\Settings\TaxSettings;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * Tax classes in the admin panel.
 *
 * The consequence worth protecting is what a delete does to the VAT charged.
 * `products.tax_class_id` is nullOnDelete and TaxSettings holds a plain int
 * rather than a foreign key, so nothing in the schema stops a delete from
 * silently retaxing the catalog — the two refusals below are the whole reason
 * this screen is not a generic CRUD.
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
function taxClassPayload(array $overrides = []): array
{
    return [
        'name' => 'Standard VAT',
        'rate' => '16',
        'is_active' => '1',
        ...$overrides,
    ];
}

test('a guest is sent to sign in rather than shown the tax classes', function () {
    $this->get(route('admin.tax-classes.index'))->assertRedirect(route('login'));
});

test('a staff member without catalog.manage is refused the tax classes', function () {
    $this->actingAs($this->support)
        ->get(route('admin.tax-classes.index'))
        ->assertForbidden();

    $this->actingAs($this->support)
        ->post(route('admin.tax-classes.store'), taxClassPayload())
        ->assertForbidden();

    expect(TaxClass::query()->count())->toBe(0);
});

test('the table lists tax classes with the number of products in each', function () {
    $standard = TaxClass::factory()->standardVat()->create(['name' => 'Aaa Standard']);
    Product::factory()->count(2)->create(['tax_class_id' => $standard->id]);
    TaxClass::factory()->zeroRated()->create(['name' => 'Zzz Zero']);

    $this->actingAs($this->manager)
        ->get(route('admin.tax-classes.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tax-classes/Index')
            ->has('taxClasses', 2)
            ->where('taxClasses.0.name', 'Aaa Standard')
            ->where('taxClasses.0.rate', '16.00')
            ->where('taxClasses.0.productCount', 2)
            ->where('taxClasses.1.productCount', 0));
});

test('the store default band is marked as such on the table', function () {
    $standard = TaxClass::factory()->standardVat()->create();

    $tax = app(TaxSettings::class);
    $tax->default_tax_class_id = $standard->id;
    $tax->save();

    $this->actingAs($this->manager)
        ->get(route('admin.tax-classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('taxClasses.0.isStoreDefault', true));
});

test('a sort column off the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.tax-classes.index', ['sort' => 'id']))
        ->assertInvalid('sort');
});

test('a tax class can be created and slugged from its name', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tax-classes.store'), taxClassPayload())
        ->assertRedirect(route('admin.tax-classes.index'));

    $created = TaxClass::query()->sole();

    expect($created->slug)->toBe('standard-vat')
        // Stored to two places whatever the staff member typed, so the edit
        // form redisplays the band exactly as the checkout will read it.
        ->and($created->rate)->toBe('16.00');
});

test('a tax class without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tax-classes.store'), taxClassPayload(['name' => '']))
        ->assertInvalid('name');

    expect(TaxClass::query()->count())->toBe(0);
});

test('a rate above 100 percent is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tax-classes.store'), taxClassPayload(['rate' => '160']))
        ->assertInvalid('rate');

    expect(TaxClass::query()->count())->toBe(0);
});

test('a rate with more than two decimal places is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tax-classes.store'), taxClassPayload(['rate' => '16.005']))
        ->assertInvalid('rate');
});

test('a slug another tax class already holds is rejected', function () {
    TaxClass::factory()->create(['slug' => 'standard-vat']);

    $this->actingAs($this->manager)
        ->post(route('admin.tax-classes.store'), taxClassPayload(['slug' => 'standard-vat']))
        ->assertInvalid('slug');
});

test('a tax class keeps its own slug when it is edited', function () {
    $taxClass = TaxClass::factory()->create(['slug' => 'standard-vat', 'rate' => 16]);

    $this->actingAs($this->manager)
        ->from(route('admin.tax-classes.edit', $taxClass))
        ->patch(route('admin.tax-classes.update', $taxClass), taxClassPayload([
            'slug' => 'standard-vat',
            'name' => 'Standard VAT (16%)',
            'rate' => '8.5',
            'is_active' => '0',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.tax-classes.index'));

    $taxClass->refresh();

    expect($taxClass->name)->toBe('Standard VAT (16%)')
        ->and($taxClass->rate)->toBe('8.50')
        ->and($taxClass->is_active)->toBeFalse();
});

test('a tax class no product is in can be deleted', function () {
    $taxClass = TaxClass::factory()->create();

    $this->actingAs($this->manager)
        ->delete(route('admin.tax-classes.destroy', $taxClass))
        ->assertRedirect(route('admin.tax-classes.index'));

    expect(TaxClass::query()->count())->toBe(0);
});

test('deleting a tax class products are still in is refused rather than orphaning them', function () {
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->create(['tax_class_id' => $taxClass->id]);

    $this->actingAs($this->manager)
        ->from(route('admin.tax-classes.edit', $taxClass))
        ->delete(route('admin.tax-classes.destroy', $taxClass))
        ->assertRedirect(route('admin.tax-classes.edit', $taxClass))
        ->assertInvalid('taxClass');

    expect(TaxClass::query()->whereKey($taxClass->id)->exists())->toBeTrue()
        ->and($product->refresh()->tax_class_id)->toBe($taxClass->id);
});

test('deleting the store default tax class is refused', function () {
    $taxClass = TaxClass::factory()->create();

    $tax = app(TaxSettings::class);
    $tax->default_tax_class_id = $taxClass->id;
    $tax->save();

    $this->actingAs($this->manager)
        ->from(route('admin.tax-classes.edit', $taxClass))
        ->delete(route('admin.tax-classes.destroy', $taxClass))
        ->assertInvalid('taxClass');

    expect(TaxClass::query()->whereKey($taxClass->id)->exists())->toBeTrue();
});

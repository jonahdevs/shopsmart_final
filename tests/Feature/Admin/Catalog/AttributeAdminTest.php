<?php

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * Attributes and their values in the admin panel.
 *
 * Every foreign key pointing at an attribute cascades, so the decisions worth
 * protecting are the refusals that stand in front of those cascades: an
 * attribute a product still uses cannot be deleted, and a value that still
 * defines a purchasable variant cannot be dropped from the form.
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
function attributePayload(array $overrides = []): array
{
    return [
        'name' => 'Bowl Capacity',
        'type' => AttributeType::Select->value,
        'is_active' => '1',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function attributeValuePayload(array $overrides = []): array
{
    return [
        'value' => '20l',
        'label' => '20 litres',
        'is_active' => '1',
        ...$overrides,
    ];
}

test('a guest is sent to sign in rather than shown the attributes', function () {
    $this->get(route('admin.attributes.index'))->assertRedirect(route('login'));
});

test('a customer is refused the attributes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.attributes.index'))
        ->assertForbidden();
});

test('a staff member without catalog.manage is refused the attributes', function () {
    $this->actingAs($this->support)
        ->get(route('admin.attributes.index'))
        ->assertForbidden();

    $this->actingAs($this->support)
        ->post(route('admin.attributes.store'), attributePayload())
        ->assertForbidden();

    expect(Attribute::query()->count())->toBe(0);
});

test('the table lists attributes with their value and product counts', function () {
    $attribute = Attribute::factory()->create(['name' => 'Colour', 'sort_order' => 0]);
    AttributeValue::factory()->count(3)->forAttribute($attribute)->create();
    ProductAttribute::query()->create([
        'product_id' => Product::factory()->create()->id,
        'attribute_id' => $attribute->id,
    ]);

    $this->actingAs($this->manager)
        ->get(route('admin.attributes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/attributes/Index')
            ->has('attributes', 1)
            ->where('attributes.0.name', 'Colour')
            ->where('attributes.0.valueCount', 3)
            ->where('attributes.0.productCount', 1));
});

test('a sort column off the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.attributes.index', ['sort' => 'is_active']))
        ->assertInvalid('sort');
});

test('an attribute is created with its values in one save', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.attributes.store'), attributePayload([
            'values' => [
                attributeValuePayload(),
                attributeValuePayload(['value' => '30l', 'label' => '30 litres']),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $attribute = Attribute::query()->sole();

    expect($attribute->slug)->toBe('bowl-capacity')
        ->and($attribute->values)->toHaveCount(2)
        ->and($attribute->values->pluck('slug')->all())->toBe(['20-litres', '30-litres']);
});

test('an attribute without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.attributes.store'), attributePayload(['name' => '']))
        ->assertInvalid('name');
});

test('a value without a label is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.attributes.store'), attributePayload([
            'values' => [attributeValuePayload(['label' => ''])],
        ]))
        ->assertInvalid('values.0.label');
});

test('a colour that is not a hex code is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.attributes.store'), attributePayload([
            'type' => AttributeType::Color->value,
            'values' => [attributeValuePayload(['color_code' => 'midnight blue'])],
        ]))
        ->assertInvalid('values.0.color_code');
});

test('two values of one attribute cannot share a slug', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.attributes.store'), attributePayload([
            'values' => [
                attributeValuePayload(['label' => '20 litres']),
                attributeValuePayload(['value' => 'twenty', 'label' => '20 Litres']),
            ],
        ]))
        ->assertInvalid('values.1.slug');

    expect(Attribute::query()->count())->toBe(0);
});

test('saving an attribute updates, adds and removes its values', function () {
    $attribute = Attribute::factory()->create();
    $kept = AttributeValue::factory()->forAttribute($attribute)->create(['label' => 'Small', 'slug' => 'small']);
    $dropped = AttributeValue::factory()->forAttribute($attribute)->create(['label' => 'Tiny', 'slug' => 'tiny']);

    $this->actingAs($this->manager)
        ->from(route('admin.attributes.edit', $attribute))
        ->patch(route('admin.attributes.update', $attribute), attributePayload([
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            'values' => [
                attributeValuePayload(['id' => $kept->id, 'value' => 'sm', 'label' => 'Small', 'slug' => 'small']),
                attributeValuePayload(['value' => 'lg', 'label' => 'Large']),
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($kept->fresh()->value)->toBe('sm')
        ->and(AttributeValue::query()->find($dropped->id))->toBeNull()
        ->and($attribute->fresh()->values->pluck('slug')->all())->toBe(['small', 'large']);
});

test('a value that still defines a variant cannot be dropped', function () {
    $attribute = Attribute::factory()->create();
    $inUse = AttributeValue::factory()->forAttribute($attribute)->create(['label' => 'Large', 'slug' => 'large']);

    ProductVariant::factory()->create()->attributeValues()->attach($inUse);

    $this->actingAs($this->manager)
        ->from(route('admin.attributes.edit', $attribute))
        ->patch(route('admin.attributes.update', $attribute), attributePayload([
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            'values' => [],
        ]))
        ->assertInvalid('values');

    expect(AttributeValue::query()->find($inUse->id))->not->toBeNull();
});

test('an attribute no product uses can be deleted, taking its values with it', function () {
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->forAttribute($attribute)->create();

    $this->actingAs($this->manager)
        ->delete(route('admin.attributes.destroy', $attribute))
        ->assertRedirect(route('admin.attributes.index'));

    expect(Attribute::query()->count())->toBe(0)
        ->and(AttributeValue::query()->find($value->id))->toBeNull();
});

test('an attribute a product still uses is refused rather than unpicking its variants', function () {
    $attribute = Attribute::factory()->create();

    ProductAttribute::query()->create([
        'product_id' => Product::factory()->create()->id,
        'attribute_id' => $attribute->id,
        'is_variation_attribute' => true,
    ]);

    $this->actingAs($this->manager)
        ->from(route('admin.attributes.index'))
        ->delete(route('admin.attributes.destroy', $attribute))
        ->assertInvalid('attribute');

    expect(Attribute::query()->count())->toBe(1);
});

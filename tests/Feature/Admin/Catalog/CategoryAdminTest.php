<?php

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * The category tree in the admin panel.
 *
 * The two decisions worth protecting are refusals. A category may not sit under
 * itself or one of its own descendants — a cycle makes every tree walk in the
 * application defensive rather than correct. And a category with children may
 * not be deleted, because `parent_id` is nullOnDelete and the delete would
 * silently promote its children to roots.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    registerAdminCatalogRoutes();

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');

    // Support is staff, and holds no `catalog.manage`.
    $this->support = User::factory()->create();
    $this->support->assignRole('Support');
});

/**
 * @return array<string, mixed>
 */
function categoryPayload(array $overrides = []): array
{
    return [
        'name' => 'Bakery Equipment',
        'status' => CategoryStatus::Active->value,
        ...$overrides,
    ];
}

// ==================================================
// WHO MAY DO WHAT
// ==================================================

test('a guest is sent to sign in rather than shown the categories', function () {
    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
});

test('a customer is refused the categories', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

test('a staff member without catalog.manage is refused the categories', function () {
    $this->actingAs($this->support)
        ->get(route('admin.categories.index'))
        ->assertForbidden();

    $this->actingAs($this->support)
        ->post(route('admin.categories.store'), categoryPayload())
        ->assertForbidden();

    expect(Category::query()->count())->toBe(0);
});

// ==================================================
// THE TREE
// ==================================================

test('the table lists the tree depth-first with each row depth', function () {
    $root = Category::factory()->create(['name' => 'Bakery', 'sort_order' => 0]);
    $child = Category::factory()->child($root)->create(['name' => 'Ovens', 'sort_order' => 0]);
    Category::factory()->child($child)->create(['name' => 'Deck ovens', 'sort_order' => 0]);
    Category::factory()->create(['name' => 'Refrigeration', 'sort_order' => 1]);

    $this->actingAs($this->manager)
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/categories/Index')
            ->has('categories', 4)
            ->where('categories.0.name', 'Bakery')
            ->where('categories.0.depth', 0)
            ->where('categories.0.childCount', 1)
            ->where('categories.1.name', 'Ovens')
            ->where('categories.1.depth', 1)
            ->where('categories.2.name', 'Deck ovens')
            ->where('categories.2.depth', 2)
            ->where('categories.3.name', 'Refrigeration')
            ->where('categories.3.depth', 0));
});

test('the product count counts a product filed both ways only once', function () {
    $category = Category::factory()->create();

    $both = Product::factory()->create(['primary_category_id' => $category->id]);
    $both->categories()->attach($category);

    Product::factory()->create(['primary_category_id' => $category->id]);

    $this->actingAs($this->manager)
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.productCount', 2));
});

test('a typed percent sign searches for itself rather than matching everything', function () {
    $literal = Category::factory()->create(['name' => 'Clearance 50% shelf']);
    Category::factory()->create(['name' => 'Clearance shelf']);

    $this->actingAs($this->manager)
        ->get(route('admin.categories.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 1)
            ->where('categories.0.id', $literal->id));
});

// ==================================================
// CREATING AND EDITING
// ==================================================

test('a category can be created and slugged from its name', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.categories.store'), categoryPayload())
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::query()->sole()->slug)->toBe('bakery-equipment');
});

test('a category without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.categories.store'), categoryPayload(['name' => '']))
        ->assertInvalid('name');
});

test('a slug another category already holds is rejected', function () {
    Category::factory()->create(['slug' => 'bakery-equipment']);

    $this->actingAs($this->manager)
        ->post(route('admin.categories.store'), categoryPayload(['slug' => 'bakery-equipment']))
        ->assertInvalid('slug');
});

test('a category can be moved under another', function () {
    $parent = Category::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.categories.edit', $category))
        ->patch(route('admin.categories.update', $category), categoryPayload([
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $parent->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->parent_id)->toBe($parent->id);
});

// ==================================================
// THE CYCLE GUARD
// ==================================================

test('a category cannot be made its own parent', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.categories.edit', $category))
        ->patch(route('admin.categories.update', $category), categoryPayload([
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->id,
        ]))
        ->assertInvalid('parent_id');

    expect($category->fresh()->parent_id)->toBeNull();
});

test('a category cannot be filed under one of its own descendants', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();
    $grandchild = Category::factory()->child($child)->create();

    $this->actingAs($this->manager)
        ->from(route('admin.categories.edit', $root))
        ->patch(route('admin.categories.update', $root), categoryPayload([
            'name' => $root->name,
            'slug' => $root->slug,
            'parent_id' => $grandchild->id,
        ]))
        ->assertInvalid('parent_id');

    expect($root->fresh()->parent_id)->toBeNull();
});

test('the parent picker never offers a category its own subtree', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();
    $other = Category::factory()->create();

    $this->actingAs($this->manager)
        ->get(route('admin.categories.edit', $root))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('parentOptions', 1)
            ->where('parentOptions.0.id', $other->id));

    expect([$root->id, $child->id])->not->toContain($other->id);
});

// ==================================================
// DELETING
// ==================================================

test('deleting a leaf category leaves its products uncategorised rather than gone', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['primary_category_id' => $category->id]);
    $product->categories()->attach($category);

    $this->actingAs($this->manager)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    $product->refresh();

    expect(Category::query()->count())->toBe(0)
        ->and($product->exists)->toBeTrue()
        ->and($product->primary_category_id)->toBeNull()
        ->and($product->categories)->toHaveCount(0);
});

test('a category with subcategories is refused rather than orphaning them', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();

    $this->actingAs($this->manager)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $root))
        ->assertInvalid('category');

    expect(Category::query()->count())->toBe(2)
        ->and($child->fresh()->parent_id)->toBe($root->id);
});

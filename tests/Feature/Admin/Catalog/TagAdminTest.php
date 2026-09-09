<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Tags\Tag;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * Tags in the admin panel.
 *
 * Two things are worth protecting here. The first is the permission boundary:
 * tags are catalog structure, so every route on them answers to
 * `catalog.manage` and a Support role holds none of them.
 *
 * The second is what a tag *is* to the storefront. The home page's Featured
 * rail and the New Arrival badge both match on a tag's name, so a name that is
 * not unique, or a delete that leaves the join rows behind, changes what
 * shoppers see. The tests below pin the name to one row and the delete to a
 * clean cascade.
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

/** A tag created the way the controller creates one, so the package slugs it. */
function makeTag(string $name): Tag
{
    /** @var Tag $tag */
    $tag = Tag::create(['name' => $name]);

    return $tag;
}

test('a guest is sent to sign in rather than shown the tags', function () {
    $this->get(route('admin.tags.index'))->assertRedirect(route('login'));
});

test('a customer is refused the tags', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.tags.index'))
        ->assertForbidden();
});

test('a staff member without catalog.manage is refused every tag route', function () {
    $tag = makeTag('Featured');
    $product = Product::factory()->create();

    $this->actingAs($this->support)->get(route('admin.tags.index'))->assertForbidden();
    $this->actingAs($this->support)->get(route('admin.tags.create'))->assertForbidden();
    $this->actingAs($this->support)->post(route('admin.tags.store'), ['name' => 'Sale'])->assertForbidden();
    $this->actingAs($this->support)->get(route('admin.tags.edit', $tag))->assertForbidden();
    $this->actingAs($this->support)->patch(route('admin.tags.update', $tag), ['name' => 'Sale'])->assertForbidden();
    $this->actingAs($this->support)->delete(route('admin.tags.destroy', $tag))->assertForbidden();

    $this->actingAs($this->support)->get(route('admin.tags.products.index', $tag))->assertForbidden();
    $this->actingAs($this->support)
        ->post(route('admin.tags.products.store', $tag), ['product_id' => $product->id])
        ->assertForbidden();
    $this->actingAs($this->support)
        ->delete(route('admin.tags.products.destroy', [$tag, $product]))
        ->assertForbidden();

    expect(Tag::query()->count())->toBe(1)
        ->and($product->fresh()->tags)->toHaveCount(0);
});

test('the table lists tags with the number of products carrying each', function () {
    $featured = makeTag('Featured');
    makeTag('Clearance');

    Product::factory()->count(2)->create()->each->attachTag($featured);

    $this->actingAs($this->manager)
        ->get(route('admin.tags.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tags/Index')
            ->has('tags', 2)
            // Default sort is by name, ascending.
            ->where('tags.0.name', 'Clearance')
            ->where('tags.0.productCount', 0)
            ->where('tags.1.name', 'Featured')
            ->where('tags.1.slug', 'featured')
            ->where('tags.1.productCount', 2));
});

test('the table can be sorted by how many products carry each tag', function () {
    $featured = makeTag('Featured');
    makeTag('Clearance');
    Product::factory()->create()->attachTag($featured);

    $this->actingAs($this->manager)
        ->get(route('admin.tags.index', ['sort' => 'products_count', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tags.0.name', 'Featured'));
});

test('a sort column off the whitelist is rejected', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.tags.index', ['sort' => 'id']))
        ->assertInvalid('sort');
});

test('a typed percent sign searches for itself rather than matching every tag', function () {
    makeTag('Save 50% Event');
    makeTag('Clearance');

    $this->actingAs($this->manager)
        ->get(route('admin.tags.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tags', 1)
            ->where('tags.0.name', 'Save 50% Event'));
});

test('a tag is created and slugged from its name', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tags.store'), ['name' => 'New Arrival'])
        ->assertRedirect(route('admin.tags.index'));

    $tag = Tag::query()->sole();

    expect($tag->name)->toBe('New Arrival')
        ->and($tag->slug)->toBe('new-arrival');
});

test('a tag without a name is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.tags.store'), ['name' => ''])
        ->assertInvalid('name');

    expect(Tag::query()->count())->toBe(0);
});

test('a name another tag already holds is rejected', function () {
    makeTag('Featured');

    $this->actingAs($this->manager)
        ->post(route('admin.tags.store'), ['name' => 'Featured'])
        ->assertInvalid('name');

    expect(Tag::query()->count())->toBe(1);
});

test('a tag keeps its own name when it is saved unchanged', function () {
    $tag = makeTag('Featured');

    $this->actingAs($this->manager)
        ->patch(route('admin.tags.update', $tag), ['name' => 'Featured'])
        ->assertSessionHasNoErrors();

    expect($tag->fresh()->name)->toBe('Featured');
});

test('a tag can be renamed', function () {
    $tag = makeTag('Featured');

    $this->actingAs($this->manager)
        ->patch(route('admin.tags.update', $tag), ['name' => 'Staff Picks'])
        ->assertRedirect(route('admin.tags.index'));

    expect($tag->fresh()->name)->toBe('Staff Picks');
});

test('the editor states how many products a delete would strip the tag from', function () {
    $tag = makeTag('Featured');
    Product::factory()->count(3)->create()->each->attachTag($tag);

    $this->actingAs($this->manager)
        ->get(route('admin.tags.edit', $tag))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tags/Form')
            ->where('tag.name', 'Featured')
            ->where('productCount', 3));
});

test('deleting a tag untags its products rather than deleting them', function () {
    $tag = makeTag('Featured');
    $product = Product::factory()->create();
    $product->attachTag($tag);

    $this->actingAs($this->manager)
        ->delete(route('admin.tags.destroy', $tag))
        ->assertRedirect(route('admin.tags.index'));

    expect(Tag::query()->count())->toBe(0)
        ->and(DB::table('taggables')->count())->toBe(0)
        ->and($product->fresh())->not->toBeNull();
});

test('the products screen lists only the products carrying the tag', function () {
    $tag = makeTag('Featured');
    $tagged = Product::factory()->create(['name' => 'Tagged mixer']);
    Product::factory()->create(['name' => 'Untagged oven']);
    $tagged->attachTag($tag);

    $this->actingAs($this->manager)
        ->get(route('admin.tags.products.index', $tag))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tags/Products')
            ->where('tag.name', 'Featured')
            ->has('products', 1)
            ->where('products.0.name', 'Tagged mixer')
            ->has('candidates', 0));
});

test('the add box offers nothing until something is typed, then only untagged products', function () {
    $tag = makeTag('Featured');
    $tagged = Product::factory()->create(['name' => 'Rondo mixer']);
    Product::factory()->create(['name' => 'Rondo oven']);
    $tagged->attachTag($tag);

    $this->actingAs($this->manager)
        ->get(route('admin.tags.products.index', [$tag, 'add' => 'Rondo']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('candidates', 1)
            ->where('candidates.0.name', 'Rondo oven'));
});

test('a product can be added to a tag and taken off it again', function () {
    $tag = makeTag('Featured');
    $product = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.tags.products.index', $tag))
        ->post(route('admin.tags.products.store', $tag), ['product_id' => $product->id])
        ->assertRedirect(route('admin.tags.products.index', $tag));

    expect($product->fresh()->tags->pluck('name')->all())->toBe(['Featured']);

    $this->actingAs($this->manager)
        ->from(route('admin.tags.products.index', $tag))
        ->delete(route('admin.tags.products.destroy', [$tag, $product]))
        ->assertRedirect(route('admin.tags.products.index', $tag));

    expect($product->fresh()->tags)->toHaveCount(0);
});

test('adding a product that does not exist is rejected outright', function () {
    $tag = makeTag('Featured');

    $this->actingAs($this->manager)
        ->post(route('admin.tags.products.store', $tag), ['product_id' => 9999])
        ->assertInvalid('product_id');

    expect(DB::table('taggables')->count())->toBe(0);
});

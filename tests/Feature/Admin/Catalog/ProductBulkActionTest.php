<?php

use App\Enums\ProductStatus;
use App\Http\Requests\Admin\ProductBulkActionRequest;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/CatalogRoutes.php';

/**
 * Bulk actions on the products table.
 *
 * The whole feature turns on one idea: the browser sends a list of ids and is
 * believed about none of them. So the tests worth having here are the ones that
 * poke at that seam — an id that does not exist, an id list longer than the cap,
 * a restore aimed at a product that was never deleted — plus the honest
 * reporting of the rows that were fine to send and still could not be acted on.
 *
 * The activity assertion is not incidental. A bulk update is the one place it
 * is tempting to write a single `UPDATE ... WHERE id IN (...)`, which is faster
 * and logs nothing at all, and the log is the only record that twenty products
 * changed price band on a Tuesday.
 */
beforeEach(function () {
    $this->withoutVite();

    config()->set('inertia.testing.ensure_pages_exist', false);

    registerAdminCatalogRoutes();

    $this->seed(PermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Manager');

    // Holds products.view and not products.manage.
    $this->support = User::factory()->create();
    $this->support->assignRole('Support');
});

// ==================================================
// WHO MAY DO WHAT
// ==================================================

test('a staff member without products.manage cannot run a bulk action', function () {
    $products = Product::factory()->count(3)->draft()->create();

    $this->actingAs($this->support)
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => $products->pluck('id')->all(),
        ])
        ->assertForbidden();

    expect(Product::query()->where('status', ProductStatus::Published)->count())->toBe(0);
});

test('a customer cannot reach the bulk endpoint at all', function () {
    $product = Product::factory()->draft()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.products.bulk'), [
            'action' => 'delete',
            'ids' => [$product->id],
        ])
        ->assertForbidden();

    expect($product->fresh()->trashed())->toBeFalse();
});

// ==================================================
// SETTING A STATUS
// ==================================================

test('a bulk status change updates every selected product', function () {
    $selected = Product::factory()->count(3)->draft()->create();
    $untouched = Product::factory()->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => $selected->pluck('id')->all(),
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHasNoErrors();

    expect(Product::query()->whereKey($selected->pluck('id'))->where('status', ProductStatus::Published)->count())->toBe(3)
        // A bulk action acts on the ids it was given and on nothing else.
        ->and($untouched->fresh()->status)->toBe(ProductStatus::Draft);
});

test('one bulk click writes one activity row per product it changed', function () {
    $products = Product::factory()->count(3)->draft()->create();

    Activity::query()->delete();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => $products->pluck('id')->all(),
        ])
        ->assertSessionHasNoErrors();

    // Three rows, not one. A mass `UPDATE` would pass every assertion above and
    // fail this one, which is the point of having it.
    expect(Activity::query()->where('log_name', 'product')->count())->toBe(3)
        ->and(Activity::query()->where('log_name', 'product')->pluck('subject_id')->sort()->values()->all())
        ->toBe($products->pluck('id')->sort()->values()->all());
});

test('the bulk bar cannot schedule products, because it cannot ask for a date', function () {
    $product = Product::factory()->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Scheduled->value,
            'ids' => [$product->id],
        ])
        ->assertInvalid('status');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

// ==================================================
// THE BIN
// ==================================================

test('a bulk delete moves every selected product to the bin', function () {
    $products = Product::factory()->count(2)->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'delete',
            'ids' => $products->pluck('id')->all(),
        ])
        ->assertSessionHasNoErrors();

    expect(Product::query()->whereKey($products->pluck('id'))->count())->toBe(0)
        ->and(Product::query()->withTrashed()->whereKey($products->pluck('id'))->count())->toBe(2);
});

test('a bulk restore only accepts products that are actually in the bin', function () {
    $binned = Product::factory()->create();
    $binned->delete();
    $live = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'only']))
        ->post(route('admin.products.bulk'), [
            'action' => 'restore',
            // The live product is the crafted half of the request.
            'ids' => [$binned->id, $live->id],
        ])
        ->assertInvalid('ids.1');

    // Nothing partial: the binned product is still binned, because the whole
    // request was refused before any row was touched.
    expect($binned->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'only']))
        ->post(route('admin.products.bulk'), [
            'action' => 'restore',
            'ids' => [$binned->id],
        ])
        ->assertSessionHasNoErrors();

    expect($binned->fresh()->trashed())->toBeFalse();
});

// ==================================================
// WHAT THE BROWSER IS NOT TRUSTED ABOUT
// ==================================================

test('an id that names no product is rejected', function () {
    $real = Product::factory()->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => [$real->id, $real->id + 9_999],
        ])
        ->assertInvalid('ids.1');

    expect($real->fresh()->status)->toBe(ProductStatus::Draft);
});

test('more ids than the cap allows are refused rather than applied', function () {
    // Every id names a real, live product, so size is the only thing wrong with
    // the request — which is what makes this a test of the cap rather than of
    // the `exists` rule.
    $products = Product::factory()->count(ProductBulkActionRequest::MAX_IDS + 1)->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'delete',
            'ids' => $products->pluck('id')->all(),
        ])
        ->assertInvalid('ids');

    // Not one of them was binned. Without the cap this single POST is a
    // catalog-wide delete.
    expect(Product::query()->count())->toBe(ProductBulkActionRequest::MAX_IDS + 1);
});

test('an unknown action is refused', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'force_delete',
            'ids' => [$product->id],
        ])
        ->assertInvalid('action');

    expect(Product::query()->count())->toBe(1);
});

// ==================================================
// THE PARTIAL RESULT
// ==================================================

test('the result names the rows it skipped and the rows it refused', function () {
    $changed = Product::factory()->draft()->create(['name' => 'Dough Sheeter']);
    $alreadyLive = Product::factory()->published()->create(['name' => 'Planetary Mixer']);
    $inTheBin = Product::factory()->draft()->create(['name' => 'Spiral Kneader']);
    $inTheBin->delete();

    // No `assertSessionHasNoErrors()` here, and not by oversight: that helper
    // starts the session store between requests, which ages the flash and eats
    // the result before the next request can read it. The status assertion
    // below proves the post was accepted just as well.
    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'with']))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => [$changed->id, $alreadyLive->id, $inTheBin->id],
        ]);

    // The eligible product was still published — one ineligible row in the
    // selection does not cost the others their change.
    expect($changed->fresh()->status)->toBe(ProductStatus::Published);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['trashed' => 'with']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bulkResult.appliedCount', 1)
            ->where('bulkResult.isClean', false)
            // Skipped is the system agreeing with you; refused is work still
            // outstanding. Both are named, because a count nobody can turn back
            // into rows is not a result.
            ->has('bulkResult.skipped', 1)
            ->where('bulkResult.skipped.0.label', 'Planetary Mixer')
            ->has('bulkResult.refused', 1)
            ->where('bulkResult.refused.0.label', 'Spiral Kneader')
            ->where('bulkResult.summary', '1 updated, 1 skipped, 1 refused.'));
});

test('a clean run reports nothing skipped or refused', function () {
    $products = Product::factory()->count(2)->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => $products->pluck('id')->all(),
        ]);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bulkResult.appliedCount', 2)
            ->where('bulkResult.isClean', true)
            ->has('bulkResult.skipped', 0)
            ->has('bulkResult.refused', 0)
            ->where('bulkResult.summary', '2 updated.'));
});

test('the result is flashed for one request and does not follow the reader around', function () {
    $product = Product::factory()->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => [$product->id],
        ]);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bulkResult'));

    // Sorting the same table must not re-show an answer to a click made two
    // screens ago.
    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('bulkResult', null));
});

// ==================================================
// THE TABLE THE BAR SITS ON
// ==================================================

test('the index offers exactly the three statuses the endpoint accepts', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('bulkStatusOptions', 3)
            ->where('bulkStatusOptions.0.value', ProductStatus::Draft->value)
            // Imperatives, not the enum's state names: the button says what the
            // click does, not where the product ends up.
            ->where('bulkStatusOptions.0.label', 'Move to draft'));
});

// ==================================================
// ONE ROW AT A TIME
// ==================================================

/*
  The Actions menu on a row posts here too, as a list of one.

  That is the point of these tests: there is no second route, no second set of
  eligibility rules and no second place the activity log is written. If a row
  menu ever grows its own endpoint, the assertions below are the ones that stop
  meaning anything, so they are written against the endpoint rather than against
  the markup that calls it.
*/

test('a one-row status change goes through the same endpoint as a bulk one', function () {
    $product = Product::factory()->draft()->create();
    $untouched = Product::factory()->draft()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => [$product->id],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->status)->toBe(ProductStatus::Published)
        ->and($untouched->fresh()->status)->toBe(ProductStatus::Draft);
});

test('a one-row status change is logged like any other', function () {
    $product = Product::factory()->draft()->create();

    Activity::query()->delete();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Archived->value,
            'ids' => [$product->id],
        ])
        ->assertSessionHasNoErrors();

    expect(Activity::query()->where('log_name', 'product')->where('subject_id', $product->id)->count())->toBe(1);
});

test('a one-row bin and a one-row restore both work', function () {
    $product = Product::factory()->published()->create();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.bulk'), [
            'action' => 'delete',
            'ids' => [$product->id],
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'only']))
        ->post(route('admin.products.bulk'), [
            'action' => 'restore',
            'ids' => [$product->id],
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->trashed())->toBeFalse();
});

test('a staff member without products.manage cannot act on a single row either', function () {
    $product = Product::factory()->published()->create();

    $this->actingAs($this->support)
        ->post(route('admin.products.bulk'), [
            'action' => 'delete',
            'ids' => [$product->id],
        ])
        ->assertForbidden();

    expect($product->fresh()->trashed())->toBeFalse();
});

test('a one-row refusal still arrives with its reason', function () {
    // A binned product cannot be given a status — the row menu hides that
    // submenu, but the refusal has to hold for a request that arrives anyway,
    // and it is the one one-row outcome the page still puts on screen.
    $product = Product::factory()->draft()->create(['name' => 'Spiral Kneader']);
    $product->delete();

    $this->actingAs($this->manager)
        ->from(route('admin.products.index', ['trashed' => 'only']))
        ->post(route('admin.products.bulk'), [
            'action' => 'status',
            'status' => ProductStatus::Published->value,
            'ids' => [$product->id],
        ]);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['trashed' => 'only']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bulkResult.isClean', false)
            ->has('bulkResult.refused', 1)
            ->where('bulkResult.refused.0.label', 'Spiral Kneader'));
});

test('each row carries the two facts its Actions menu is drawn from', function () {
    $live = Product::factory()->published()->create(['name' => 'Aaa Live']);
    $draft = Product::factory()->draft()->create(['name' => 'Bbb Draft']);
    $hidden = Product::factory()->published()->hidden()->create(['name' => 'Ccc Hidden']);

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // The state itself, not just its label: the "Set status" submenu
            // has to know which entry to grey out, and a translated label is
            // the wrong thing to compare against the endpoint's option values.
            ->where('products.0.status', ProductStatus::Published->value)
            ->where('products.1.status', ProductStatus::Draft->value)
            // "View on store" is offered exactly when the storefront would
            // render the page. A draft 404s, and so does a published product
            // whose visibility is Hidden.
            ->where('products.0.isViewableOnStore', true)
            ->where('products.1.isViewableOnStore', false)
            ->where('products.2.isViewableOnStore', false));

    expect($live->isViewableOnStore())->toBeTrue()
        ->and($draft->isViewableOnStore())->toBeFalse()
        ->and($hidden->isViewableOnStore())->toBeFalse();
});

test('a binned product is never viewable on the store', function () {
    $product = Product::factory()->published()->create();
    $product->delete();

    $this->actingAs($this->manager)
        ->get(route('admin.products.index', ['trashed' => 'only']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.isDeleted', true)
            ->where('products.0.isViewableOnStore', false));
});

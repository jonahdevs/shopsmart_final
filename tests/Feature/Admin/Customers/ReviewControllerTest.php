<?php

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Settings\ReviewSettings;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/**
 * The review moderation queue.
 *
 * The decisions worth protecting are what a moderator's verdict does to the
 * storefront — an approved review appears on the product page and a rejected
 * one does not — and that a review whose author has closed their account is
 * still moderatable rather than a crash.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->seed(PermissionSeeder::class);

    $this->moderator = User::factory()->create();
    $this->moderator->assignRole('Support');
});

/**
 * A staff member holding every permission except the ones named.
 *
 * Every seeded role carries `reviews.manage`, so proving the `can:` middleware
 * is what refuses the request needs a role built for the purpose.
 */
function staffWithoutReviewPermission(string ...$withheld): User
{
    $role = Role::create([
        'name' => 'Restricted '.Str::random(8),
        'guard_name' => PermissionSeeder::GUARD,
    ]);

    $role->syncPermissions(array_values(array_diff(PermissionSeeder::PERMISSIONS, $withheld)));

    $staff = User::factory()->create();
    $staff->assignRole($role);

    return $staff;
}

describe('index', function () {
    test('it lists reviews with the pending count and the auto-approve setting', function () {
        Review::factory()->pending()->count(2)->create();
        Review::factory()->approved()->create();

        app(ReviewSettings::class)->fill(['auto_approve' => true])->save();

        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/reviews/Index')
                ->has('reviews', 3)
                ->where('pendingCount', 2)
                ->where('autoApprove', true));
    });

    test('it narrows the queue to one status', function () {
        $pending = Review::factory()->pending()->create();
        Review::factory()->approved()->create();

        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index', ['status' => ReviewStatus::Pending->value]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.id', $pending->id));
    });

    test('it narrows the queue to one rating', function () {
        $onestar = Review::factory()->create(['rating' => 1]);
        Review::factory()->create(['rating' => 5]);

        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index', ['rating' => 1]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.id', $onestar->id));
    });

    test('it renders a review whose author has deleted their account', function () {
        $orphan = Review::factory()->pending()->create([
            'user_id' => null,
            'author_name' => 'Amina Wanjiru',
        ]);

        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.id', $orphan->id)
                ->where('reviews.0.authorName', 'Amina Wanjiru')
                ->where('reviews.0.customerId', null));
    });

    test('it treats a typed percent sign as a literal rather than a wildcard', function () {
        $literal = Review::factory()->create(['author_name' => 'Fifty% Off Fan']);
        Review::factory()->create(['author_name' => 'Someone Else', 'title' => null, 'body' => 'Plain words']);

        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index', ['search' => '%']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.id', $literal->id));
    });

    test('it rejects a rating outside one to five', function () {
        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index', ['rating' => 9]))
            ->assertSessionHasErrors('rating');
    });

    test('it rejects a sort column that is not on the allow list', function () {
        $this->actingAs($this->moderator)
            ->get(route('admin.reviews.index', ['sort' => 'body']))
            ->assertSessionHasErrors('sort');
    });

    test('it returns 403 for a staff member without reviews.manage', function () {
        $this->actingAs(staffWithoutReviewPermission('reviews.manage'))
            ->get(route('admin.reviews.index'))
            ->assertForbidden();
    });

    test('it returns 403 for a customer', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.reviews.index'))
            ->assertForbidden();
    });

    test('it redirects a guest to sign in', function () {
        $this->get(route('admin.reviews.index'))->assertRedirect(route('login'));
    });
});

describe('update', function () {
    test('approving a review publishes it on the product page', function () {
        $product = Product::factory()->published()->create();
        $review = Review::factory()->pending()->create(['product_id' => $product->id]);

        $this->actingAs($this->moderator)
            ->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Approved->value])
            ->assertRedirect();

        expect($review->refresh()->status)->toBe(ReviewStatus::Approved)
            ->and($review->approved_at)->not->toBeNull();

        // The product page defers its reviews, so the published list only
        // arrives on the follow-up request.
        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->has('reviews', 1)
                    ->where('reviews.0.id', $review->id)));
    });

    test('rejecting a review takes it off the product page and clears the approval stamp', function () {
        $product = Product::factory()->published()->create();
        $review = Review::factory()->approved()->create(['product_id' => $product->id]);

        $this->actingAs($this->moderator)
            ->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Rejected->value])
            ->assertRedirect();

        expect($review->refresh()->status)->toBe(ReviewStatus::Rejected)
            ->and($review->approved_at)->toBeNull();

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->has('reviews', 0)));
    });

    test('it moderates a review whose author has deleted their account', function () {
        $review = Review::factory()->pending()->create(['user_id' => null]);

        $this->actingAs($this->moderator)
            ->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Approved->value])
            ->assertRedirect();

        expect($review->refresh()->status)->toBe(ReviewStatus::Approved);
    });

    test('it refuses a status a moderator never chooses', function () {
        $review = Review::factory()->approved()->create();

        $this->actingAs($this->moderator)
            ->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Pending->value])
            ->assertSessionHasErrors(['status' => 'A review can only be approved or rejected.']);

        expect($review->refresh()->status)->toBe(ReviewStatus::Approved);
    });

    test('it returns 403 for a staff member without reviews.manage', function () {
        $review = Review::factory()->pending()->create();

        $this->actingAs(staffWithoutReviewPermission('reviews.manage'))
            ->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Approved->value])
            ->assertForbidden();

        expect($review->refresh()->status)->toBe(ReviewStatus::Pending);
    });

    test('it redirects a guest to sign in', function () {
        $review = Review::factory()->pending()->create();

        $this->patch(route('admin.reviews.update', $review), ['status' => ReviewStatus::Approved->value])
            ->assertRedirect(route('login'));
    });
});

describe('destroy', function () {
    test('it removes the review', function () {
        $review = Review::factory()->pending()->create();

        $this->actingAs($this->moderator)
            ->delete(route('admin.reviews.destroy', $review))
            ->assertRedirect();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    });

    test('it returns 403 for a staff member without reviews.manage', function () {
        $review = Review::factory()->pending()->create();

        $this->actingAs(staffWithoutReviewPermission('reviews.manage'))
            ->delete(route('admin.reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    });

    test('it returns 403 for a customer', function () {
        $review = Review::factory()->pending()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    });
});

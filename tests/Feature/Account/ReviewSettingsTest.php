<?php

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Settings\ReviewSettings;

/**
 * The three review settings actually govern the feature.
 *
 * Their defaults match what the code used to hard-code, so nothing changes for
 * a store that leaves them alone — which is exactly why they need pinning here.
 * A setting that is only ever exercised at its default value is indistinguishable
 * from a setting that does nothing, and this store ships an admin screen that
 * offers all three.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->customer = User::factory()->create();
    $this->product = Product::factory()->published()->create();
});

/** A completed order for this customer containing the product. */
function reviewableOrder(User $user, Product $product): Order
{
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Completed,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
    ]);

    return $order;
}

/** @param array<string, bool> $overrides */
function reviewSettings(array $overrides): void
{
    $settings = app(ReviewSettings::class);

    foreach ($overrides as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();
}

/** @return array<string, mixed> */
function reviewPayload(): array
{
    return [
        'rating' => 5,
        'body' => 'Solid build, arrived on time, and it has handled everything I have thrown at it.',
    ];
}

test('closing reviews takes the form away', function () {
    reviewableOrder($this->customer, $this->product);
    reviewSettings(['reviews_enabled' => false]);

    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertNotFound();
});

test('closing reviews refuses a submission', function () {
    reviewableOrder($this->customer, $this->product);
    reviewSettings(['reviews_enabled' => false]);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), reviewPayload())
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('reviews', 0);
});

test('dropping the purchase requirement lets any customer review', function () {
    // No order at all — under the default rules this customer could not review.
    reviewSettings(['require_verified_purchase' => false]);

    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertOk();

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), reviewPayload())
        ->assertRedirect(route('account.reviews'));

    $review = Review::query()->sole();

    expect($review->status)->toBe(ReviewStatus::Pending)
        // The badge still tells the truth: this reviewer did not buy it, even
        // though the store no longer insists on that.
        ->and($review->verified_purchase)->toBeFalse();
});

test('a real purchase is still badged when the requirement is off', function () {
    reviewableOrder($this->customer, $this->product);
    reviewSettings(['require_verified_purchase' => false]);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), reviewPayload());

    expect(Review::query()->sole()->verified_purchase)->toBeTrue();
});

test('auto approval publishes a review immediately', function () {
    reviewableOrder($this->customer, $this->product);
    reviewSettings(['auto_approve' => true]);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), reviewPayload())
        ->assertRedirect(route('account.reviews'));

    $review = Review::query()->sole();

    expect($review->status)->toBe(ReviewStatus::Approved)
        ->and($review->approved_at)->not->toBeNull();
});

test('the moderated default holds a review back', function () {
    reviewableOrder($this->customer, $this->product);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), reviewPayload());

    $review = Review::query()->sole();

    expect($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->approved_at)->toBeNull();
});

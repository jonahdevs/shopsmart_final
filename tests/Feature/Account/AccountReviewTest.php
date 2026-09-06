<?php

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Who may review what, and what a submitted review becomes.
 *
 * The rule the store trades on is that a review comes from someone who received
 * the product: a line on a completed order. A paid order still in transit is
 * deliberately not enough — the shopper has not seen the thing yet.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 6 page components are built by another agent from the props
    // asserted here.
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->customer = User::factory()->create();
    $this->product = Product::factory()->published()->create(['name' => 'Ridgeline Drill']);
});

/** An order for this customer at the given status, containing the product. */
function orderContaining(User $user, Product $product, OrderStatus $status): Order
{
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => $status]);

    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

    return $order;
}

test('a customer who received the product can open the review form', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Completed);

    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('account/ReviewForm')
            ->where('product.id', $this->product->id)
            ->where('product.name', 'Ridgeline Drill')
            ->has('breadcrumbs', 2));
});

test('the review form is not found for a product the customer never bought', function () {
    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertNotFound();
});

test('the review form is not found while the order is still undelivered', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Processing);

    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertNotFound();
});

test('the review form is not found once the customer has reviewed the product', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Completed);
    Review::factory()->create(['user_id' => $this->customer->id, 'product_id' => $this->product->id]);

    $this->actingAs($this->customer)
        ->get(route('account.reviews.create', $this->product))
        ->assertNotFound();
});

test('a delivered purchase can be reviewed and lands in the moderation queue', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Completed);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), [
            'rating' => 5,
            'title' => 'Does exactly what it says',
            'body' => 'Drove forty screws into hardwood on one charge and never slowed down.',
        ])
        ->assertRedirect(route('account.reviews'))
        ->assertSessionHasNoErrors();

    $review = Review::query()->sole();

    expect($review->product_id)->toBe($this->product->id)
        ->and($review->user_id)->toBe($this->customer->id)
        ->and($review->author_name)->toBe($this->customer->name)
        ->and($review->rating)->toBe(5)
        ->and($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->verified_purchase)->toBeTrue()
        ->and($review->approved_at)->toBeNull();
});

test('a customer cannot review a product they never bought', function () {
    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), [
            'rating' => 5,
            'body' => 'I have never touched one of these but it looks lovely in the photos.',
        ])
        ->assertSessionHasErrors(['body' => 'You can only review a product once an order containing it has been delivered.']);

    expect(Review::query()->count())->toBe(0);
});

test('a customer cannot review a product whose order has not been delivered', function () {
    orderContaining($this->customer, $this->product, OrderStatus::OutForDelivery);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), [
            'rating' => 4,
            'body' => 'The courier says it is nearly here, which is promising enough for four stars.',
        ])
        ->assertSessionHasErrors('body');

    expect(Review::query()->count())->toBe(0);
});

test('a customer cannot review the same product twice', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Completed);

    $payload = [
        'rating' => 5,
        'body' => 'Drove forty screws into hardwood on one charge and never slowed down.',
    ];

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), $payload)
        ->assertSessionHasNoErrors();

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), $payload)
        ->assertSessionHasErrors(['body' => 'You have already reviewed this product.']);

    expect(Review::query()->count())->toBe(1);
});

test('another customer completed order does not license a review', function () {
    $stranger = User::factory()->create();
    orderContaining($stranger, $this->product, OrderStatus::Completed);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), [
            'rating' => 5,
            'body' => 'Someone else bought this one, but I am sure it is excellent.',
        ])
        ->assertSessionHasErrors('body');

    expect(Review::query()->count())->toBe(0);
});

test('a review needs a rating in range and a body with something in it', function () {
    orderContaining($this->customer, $this->product, OrderStatus::Completed);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), [])
        ->assertSessionHasErrors(['rating', 'body']);

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), ['rating' => 6, 'body' => 'Six stars, easily, no question about it.'])
        ->assertSessionHasErrors('rating');

    $this->actingAs($this->customer)
        ->post(route('account.reviews.store', $this->product), ['rating' => 5, 'body' => 'Great'])
        ->assertSessionHasErrors('body');

    expect(Review::query()->count())->toBe(0);
});

test('the reviews page lists what this customer wrote, with its moderation state', function () {
    $mine = Review::factory()->pending()->create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
    ]);

    Review::factory()->approved()->create(['user_id' => User::factory(), 'product_id' => $this->product->id]);

    $this->actingAs($this->customer)
        ->get(route('account.reviews'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('account/Reviews')
            ->has('reviews', 1)
            ->where('reviews.0.id', $mine->id)
            ->where('reviews.0.status', ReviewStatus::Pending->value)
            ->where('reviews.0.product.id', $this->product->id)
            ->has('breadcrumbs', 2));
});

test('the reviews page defers the products still waiting to be reviewed', function () {
    $reviewed = Product::factory()->published()->create();

    orderContaining($this->customer, $this->product, OrderStatus::Completed);
    orderContaining($this->customer, $reviewed, OrderStatus::Completed);
    Review::factory()->create(['user_id' => $this->customer->id, 'product_id' => $reviewed->id]);

    $this->actingAs($this->customer)
        ->get(route('account.reviews'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('awaitingReview')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('awaitingReview', 1)
                ->where('awaitingReview.0.id', $this->product->id)));
});

test('a guest cannot reach the review pages', function () {
    $this->get(route('account.reviews'))->assertRedirect(route('login'));
    $this->get(route('account.reviews.create', $this->product))->assertRedirect(route('login'));
    $this->post(route('account.reviews.store', $this->product))->assertRedirect(route('login'));
});

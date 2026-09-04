<?php

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\RecentlyViewed;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('approved scope returns only approved reviews', function () {
    $approved = Review::factory()->approved()->create();
    Review::factory()->pending()->create();
    Review::factory()->rejected()->create();

    expect(Review::approved()->pluck('id')->all())->toBe([$approved->id]);
});

test('approving a review stamps approved_at', function () {
    $review = Review::factory()->pending()->create();

    $review->approve();

    expect($review->refresh()->status)->toBe(ReviewStatus::Approved)
        ->and($review->approved_at)->not->toBeNull();
});

test('rejecting a review clears approved_at', function () {
    $review = Review::factory()->approved()->create();

    $review->reject();

    expect($review->refresh()->status)->toBe(ReviewStatus::Rejected)
        ->and($review->approved_at)->toBeNull();
});

test('recording a recently viewed product twice keeps one row and moves viewed_at forward', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->travelTo(now()->subHour());
    RecentlyViewed::record($user, $product);
    $firstViewedAt = RecentlyViewed::sole()->viewed_at;

    $this->travelBack();
    RecentlyViewed::record($user, $product);

    expect(RecentlyViewed::count())->toBe(1)
        ->and(RecentlyViewed::sole()->viewed_at->greaterThan($firstViewedAt))->toBeTrue();
});

test('a product view is throttled per session for thirty minutes', function () {
    Cache::flush();

    $product = Product::factory()->create();

    ProductView::record($product, null, 'session-a');
    ProductView::record($product, null, 'session-a');

    expect(ProductView::count())->toBe(1);

    ProductView::record($product, null, 'session-b');

    expect(ProductView::count())->toBe(2);
});

test('a product view is recorded again once the throttle window expires', function () {
    Cache::flush();

    $product = Product::factory()->create();
    $user = User::factory()->create();

    ProductView::record($product, $user, 'session-a');

    $this->travel(31)->minutes();
    ProductView::record($product, $user, 'session-a');

    expect(ProductView::count())->toBe(2)
        ->and(ProductView::pluck('user_id')->all())->toBe([$user->id, $user->id]);
});

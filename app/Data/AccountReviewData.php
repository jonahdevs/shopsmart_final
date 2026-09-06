<?php

namespace App\Data;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One review as its own author sees it.
 *
 * Deliberately not {@see ReviewData}: that shape is the public one and carries
 * only approved reviews, so it has no reason to know about moderation. A
 * shopper looking at their own contributions does — a review sitting in the
 * queue must say so, otherwise it reads as lost.
 *
 * The product travels as a {@see ProductCardData} so the account page renders
 * the same tile the rest of the storefront does.
 */
#[TypeScript]
class AccountReviewData extends Data
{
    public function __construct(
        public int $id,
        public ProductCardData $product,
        public int $rating,
        public ?string $title,
        public string $body,
        public ReviewStatus $status,
        public string $statusLabel,
        public string $statusVariant,
        public bool $verifiedPurchase,
        public ?string $submittedAt,
        public ?string $submittedAtForHumans,
    ) {}

    /**
     * Expects `product` (with its media and review stats) to be loaded — the
     * account reviews page eager-loads them so a page of reviews costs a fixed
     * number of queries.
     */
    public static function fromModel(Review $review, ProductCardData $product): self
    {
        return new self(
            id: $review->getKey(),
            product: $product,
            rating: $review->rating,
            title: $review->title,
            body: $review->body,
            status: $review->status,
            statusLabel: $review->status->label(),
            statusVariant: $review->status->badgeVariant(),
            verifiedPurchase: $review->verified_purchase,
            submittedAt: $review->created_at?->toIso8601String(),
            submittedAtForHumans: $review->created_at?->diffForHumans(),
        );
    }
}

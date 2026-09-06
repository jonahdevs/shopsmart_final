<?php

namespace App\Data;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One review as a moderator reads it, in the queue or on a customer's page.
 *
 * `authorName` is the review's own snapshotted column, never the account's
 * current name — that is what keeps a review attributable after the reviewer
 * closes their account. `customerId` is therefore null far more often than it
 * looks: for imported and anonymous reviews, and for every review whose author
 * has since deleted their account. Nothing here may assume it is set.
 *
 * The body travels in full because moderating a review means reading it; a
 * truncated one would send staff to a second page to make the decision.
 */
#[TypeScript]
class AdminReviewRowData extends Data
{
    public function __construct(
        public int $id,
        public ReviewStatus $status,
        public string $statusLabel,
        public string $statusVariant,
        public int $rating,
        public string $authorName,
        /** Null for an anonymous, imported, or closed-account review. */
        public ?int $customerId,
        public bool $verifiedPurchase,
        public ?string $title,
        public string $body,
        /** Null once the product behind the review has been deleted. */
        public ?int $productId,
        public string $productName,
        public ?string $productSlug,
        public ?string $approvedAt,
        public string $submittedAt,
    ) {}

    public static function fromModel(Review $review): self
    {
        $product = $review->product;

        return new self(
            id: $review->getKey(),
            status: $review->status,
            statusLabel: $review->status->label(),
            statusVariant: $review->status->badgeVariant(),
            rating: $review->rating,
            authorName: $review->author_name,
            customerId: $review->user_id,
            verifiedPurchase: $review->verified_purchase,
            title: $review->title,
            body: $review->body,
            productId: $product?->getKey(),
            productName: $product === null ? __('Deleted product') : $product->name,
            productSlug: $product?->slug,
            approvedAt: $review->approved_at?->toIso8601String(),
            submittedAt: $review->created_at?->toIso8601String() ?? '',
        );
    }
}

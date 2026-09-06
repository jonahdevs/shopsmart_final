<?php

namespace App\Data;

use App\Enums\ReviewAuthorFormat;
use App\Models\Review;
use App\Settings\ReviewSettings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One approved customer review. `authorName` is the snapshot taken when the
 * review was left, so it survives the reviewer deleting their account — which
 * is exactly why it is rendered through the store's
 * {@see ReviewAuthorFormat} here rather than handed to the client
 * whole. This is the only place a reviewer's name reaches the public
 * storefront.
 */
#[TypeScript]
class ReviewData extends Data
{
    public function __construct(
        public int $id,
        public string $authorName,
        public int $rating,
        public ?string $title,
        public string $body,
        public bool $verifiedPurchase,
        public ?string $publishedAt,
        public ?string $publishedAtForHumans,
    ) {}

    public static function fromModel(Review $review): self
    {
        $published = $review->approved_at ?? $review->created_at;

        return new self(
            id: $review->getKey(),
            // Resolved rather than injected: the settings class is a container
            // singleton, so a page of reviews reads it once.
            authorName: app(ReviewSettings::class)->authorFormat()->apply($review->author_name),
            rating: $review->rating,
            title: $review->title,
            body: $review->body,
            verifiedPurchase: $review->verified_purchase,
            publishedAt: $published?->toIso8601String(),
            publishedAtForHumans: $published?->diffForHumans(),
        );
    }
}

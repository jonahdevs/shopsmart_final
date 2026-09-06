<?php

namespace App\Support;

use App\Data\ProductCardData;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Settings\ReviewSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who may review what.
 *
 * The store only publishes reviews from people who actually received the thing:
 * a line on a {@see OrderStatus::Completed} order is the proof, because that is
 * the state an order reaches once it has been delivered or collected. A paid
 * order still in transit is not enough — the shopper has not seen the product
 * yet, so an opinion about it is not a purchase review.
 *
 * The same rules serve three call sites (the form request, the review form, and
 * the "waiting for your review" list), so they live here rather than being
 * restated in each — a mismatch between them would either offer a form that
 * cannot submit or hide one that could.
 */
class ReviewEligibility
{
    public function __construct(private ReviewSettings $settings) {}

    /** Whether the store is collecting reviews at all. */
    public function enabled(): bool
    {
        return $this->settings->reviews_enabled;
    }

    /**
     * Whether new reviews publish immediately or wait for a moderator.
     *
     * Read by the caller that creates the review rather than applied here, so
     * this class stays a set of yes/no answers about a person and a product.
     */
    public function publishesImmediately(): bool
    {
        return $this->settings->auto_approve;
    }

    /**
     * The single question the form, the form request and the waiting list all
     * ask: may this person write about this product right now?
     *
     * Purchase proof is required only while `require_verified_purchase` is on.
     * With it off, any signed-in customer may review anything — which is what
     * that setting means, and until now it meant nothing at all because the
     * rule below was hard-coded.
     */
    public function canReview(User $user, Product $product): bool
    {
        if (! $this->enabled() || $this->hasReviewed($user, $product)) {
            return false;
        }

        return ! $this->settings->require_verified_purchase
            || $this->hasDeliveredPurchase($user, $product);
    }

    /** Whether this customer bought this product on an order that completed. */
    public function hasDeliveredPurchase(User $user, Product $product): bool
    {
        return $this->deliveredOrders($user)
            ->whereHas('items', fn (Builder $query): Builder => $query->where('product_id', $product->getKey()))
            ->exists();
    }

    /**
     * Whether this customer has already reviewed this product.
     *
     * There is no unique index behind (user_id, product_id) — the table also
     * holds imported and anonymous reviews, where a null user_id would make one
     * meaningless — so "one review per customer per product" is enforced here
     * and in the form request rather than by the schema.
     */
    public function hasReviewed(User $user, Product $product): bool
    {
        return Review::query()
            ->where('user_id', $user->getKey())
            ->where('product_id', $product->getKey())
            ->exists();
    }

    /**
     * Everything this customer received and has not written about yet, newest
     * purchase first.
     *
     * Loaded the way a product card wants it, so the caller can hand each row
     * straight to {@see ProductCardData::fromModel()}.
     *
     * @return Collection<int, Product>
     */
    public function productsAwaitingReview(User $user, int $limit = 12): Collection
    {
        $productIds = array_values(array_diff(
            $this->deliveredProductIds($user),
            $this->reviewedProductIds($user),
        ));

        if ($productIds === []) {
            /** @var Collection<int, Product> */
            return Product::query()->whereRaw('1 = 0')->get();
        }

        return Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->whereIn('id', $productIds)
            ->orderByDesc('id')
            ->take($limit)
            ->get();
    }

    /** How many products are waiting, without hydrating any of them. */
    public function awaitingReviewCount(User $user): int
    {
        return count(array_diff(
            $this->deliveredProductIds($user),
            $this->reviewedProductIds($user),
        ));
    }

    /**
     * Distinct products on this customer's completed orders.
     *
     * A line whose product has since been deleted carries a null product_id and
     * drops out — there is nothing left to review.
     *
     * @return list<int>
     */
    private function deliveredProductIds(User $user): array
    {
        /** @var list<int> $ids */
        $ids = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn (Builder $query): Builder => $query
                ->where('user_id', $user->getKey())
                ->where('status', OrderStatus::Completed))
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function reviewedProductIds(User $user): array
    {
        /** @var list<int> $ids */
        $ids = Review::query()
            ->where('user_id', $user->getKey())
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * @return Builder<Order>
     */
    private function deliveredOrders(User $user): Builder
    {
        return Order::query()
            ->where('user_id', $user->getKey())
            ->where('status', OrderStatus::Completed);
    }
}

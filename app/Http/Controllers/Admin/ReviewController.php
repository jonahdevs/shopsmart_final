<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminReviewRowData;
use App\Data\PaginationData;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIndexRequest;
use App\Http\Requests\Admin\UpdateReviewStatusRequest;
use App\Models\Review;
use App\Settings\ReviewSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review moderation queue.
 *
 * One permission covers the whole section: moderating is the only thing anyone
 * does to a review, so there is no read-without-write role to carve out the way
 * there is for orders.
 *
 * Approving and rejecting go through {@see Review::approve()} and
 * {@see Review::reject()} rather than a direct write, because those two methods
 * are what keep `approved_at` telling the truth about `status` — and
 * `Product::withReviewStats()` aggregates through `Review::approved()`, so a
 * status written any other way would silently move a product's star rating.
 *
 * Whether a review needs a moderator at all is {@see ReviewSettings::$auto_approve}'s
 * decision, made where the review is created. This page only reports it, so a
 * moderator looking at an empty queue knows why it is empty.
 */
class ReviewController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the moderation queue. */
    private const PER_PAGE = 20;

    public function index(ReviewIndexRequest $request, ReviewSettings $settings): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';

        $reviews = Review::query()
            // Eager-loaded rather than lazily touched by the Data object, which
            // would otherwise be one product query per row.
            ->with('product:id,name,slug')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/reviews/Index', [
            'reviews' => array_values(array_map(
                fn (Review $review): AdminReviewRowData => AdminReviewRowData::fromModel($review),
                $reviews->items(),
            )),
            'pagination' => PaginationData::fromPaginator($reviews),
            'filters' => [
                'search' => $request->validated('search'),
                'status' => $request->validated('status'),
                'rating' => $request->validated('rating'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => ReviewStatus::options(),
            'pendingCount' => Review::query()->pending()->count(),
            'autoApprove' => $settings->auto_approve,
            'reviewsEnabled' => $settings->reviews_enabled,
        ]);
    }

    /**
     * Publish a review or pull it back out of public view.
     */
    public function update(UpdateReviewStatusRequest $request, Review $review): RedirectResponse
    {
        $status = ReviewStatus::from((string) $request->validated('status'));

        if ($status === ReviewStatus::Approved) {
            $review->approve();
            $message = __('Review approved.');
        } else {
            $review->reject();
            $message = __('Review rejected.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Remove a review outright.
     *
     * Rejecting is the usual answer — it keeps the record of what was said and
     * that somebody looked at it. Deletion is for the review that should never
     * have existed, and it is irreversible, which is why the page asks first.
     */
    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review deleted.')]);

        return back();
    }

    /**
     * Narrow the queue by the filter bar.
     *
     * The search covers the review itself and the product it is about, because
     * a moderator arrives here from one of two directions: a complaint about a
     * specific product, or a name that keeps posting.
     *
     * @param  Builder<Review>  $query
     */
    private function applyFilters(Builder $query, ReviewIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('author_name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('title'), [$pattern])
                    ->orWhereRaw($this->likeExpression('body'), [$pattern])
                    ->orWhereHas('product', fn (Builder $product): Builder => $product
                        ->whereRaw($this->likeExpression('name'), [$pattern]));
            });
        }

        $query
            ->when($request->validated('status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->validated('rating'), fn (Builder $q, int $rating) => $q->where('rating', $rating));
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Data\BreadcrumbData;
use App\Data\ProductCardData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReviewRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\ReviewEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writing a review of something you bought.
 *
 * The form is only reachable for a product the shopper actually received and
 * has not already written about; anything else is a 404, because the form does
 * not exist for them. The same two conditions are re-checked by
 * {@see StoreReviewRequest} on submit — the form request is the authority, this
 * check only stops a dead form from rendering.
 */
class ReviewController extends Controller
{
    public function create(Request $request, Product $product, ReviewEligibility $eligibility): Response
    {
        $user = $this->customer($request);

        abort_unless($eligibility->canReview($user, $product), 404);

        $product->load(['brand:id,name,slug', 'media']);
        $product->loadReviewStatsIfMissing();

        return Inertia::render('account/ReviewForm', [
            'product' => ProductCardData::fromModel($product),
            'breadcrumbs' => $this->breadcrumbs(__('Review :name', ['name' => $product->name])),
        ]);
    }

    public function store(StoreReviewRequest $request, Product $product): RedirectResponse
    {
        Review::query()->create($request->reviewAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks — your review of :name is with our team.', ['name' => $product->name]),
        ]);

        // Not `back()`: the form the shopper just posted from now 404s, because
        // they have used up their one review of this product. The reviews page
        // is where the review they just wrote is, so that is where they go.
        return to_route('account.reviews');
    }

    private function customer(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(string $current): array
    {
        return [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: $current, slug: null),
        ];
    }
}

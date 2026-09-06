<?php

namespace App\Http\Controllers\Account;

use App\Data\AccountReviewData;
use App\Data\AccountStatsData;
use App\Data\AddressData;
use App\Data\BreadcrumbData;
use App\Data\OrderData;
use App\Data\ProductCardData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\OrderController;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecentlyViewed;
use App\Models\Review;
use App\Models\User;
use App\Support\ReviewEligibility;
use App\Support\StorefrontSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's own corner of the storefront.
 *
 * Every page here reads only the signed-in user's rows — there is no id in any
 * of these routes, so there is nothing to tamper with. The pages that do take
 * an id (an order, an address) live on their own controllers and check
 * ownership with a 404.
 *
 * The dashboard is the page most likely to grow a query problem, because every
 * new panel someone adds to it is another SELECT on the first screen after
 * login. It is held to a fixed shape: three counts, three orders, and one rail
 * that is deferred so it never delays the first paint.
 */
class AccountController extends Controller
{
    /** Orders shown on the dashboard before the shopper clicks through. */
    private const RECENT_ORDERS = 3;

    /** Products in the "recently viewed" rail on the dashboard. */
    private const DASHBOARD_RAIL = 8;

    /** Products on the dedicated recently-viewed page. */
    private const RECENTLY_VIEWED_LIMIT = 24;

    /** Reviews listed before the shopper is asked to scroll. */
    private const REVIEW_LIMIT = 20;

    public function dashboard(Request $request, StorefrontSession $storefront, ReviewEligibility $eligibility): Response
    {
        $user = $this->customer($request);

        return Inertia::render('account/Dashboard', [
            'customerName' => $user->name,
            'stats' => new AccountStatsData(
                orderCount: Order::query()->where('user_id', $user->getKey())->count(),
                addressCount: Address::query()->where('user_id', $user->getKey())->count(),
                wishlistCount: $storefront->shopperState()->wishlistCount,
                awaitingReviewCount: $eligibility->awaitingReviewCount($user),
            ),
            'recentOrders' => $this->recentOrders($user),
            'defaultAddress' => $this->defaultAddress($user),
            // Below the fold and nothing above it depends on it, so the page
            // paints before this query runs.
            'recentlyViewed' => Inertia::defer(fn (): array => $this->recentlyViewedCards($user, self::DASHBOARD_RAIL)),
            'breadcrumbs' => $this->breadcrumbs(__('Your account')),
        ]);
    }

    public function addresses(Request $request): Response
    {
        $user = $this->customer($request);

        return Inertia::render('account/Addresses', [
            'addresses' => array_values(Address::query()
                ->where('user_id', $user->getKey())
                ->inPickOrder()
                ->get()
                ->map(fn (Address $address): AddressData => AddressData::fromModel($address))
                ->all()),
            'breadcrumbs' => $this->breadcrumbs(__('Your addresses')),
        ]);
    }

    public function reviews(Request $request, ReviewEligibility $eligibility): Response
    {
        $user = $this->customer($request);

        return Inertia::render('account/Reviews', [
            'reviews' => $this->writtenReviews($user),
            // The second half of the page: what the shopper could still write
            // about. Deferred because it is two extra queries behind a list the
            // page can already render.
            'awaitingReview' => Inertia::defer(fn (): array => array_values($eligibility
                ->productsAwaitingReview($user)
                ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
                ->all())),
            'breadcrumbs' => $this->breadcrumbs(__('Your reviews')),
        ]);
    }

    public function recentlyViewed(Request $request): Response
    {
        $user = $this->customer($request);

        return Inertia::render('account/RecentlyViewed', [
            'products' => $this->recentlyViewedCards($user, self::RECENTLY_VIEWED_LIMIT),
            'breadcrumbs' => $this->breadcrumbs(__('Recently viewed')),
        ]);
    }

    /**
     * The shopper's most recent orders, with their lines, for the dashboard.
     *
     * @return list<OrderData>
     */
    private function recentOrders(User $user): array
    {
        return array_values(Order::query()
            ->with('items')
            ->forCustomer((int) $user->getKey())
            ->take(self::RECENT_ORDERS)
            ->get()
            ->map(fn (Order $order): OrderData => OrderData::fromModel($order))
            ->all());
    }

    private function defaultAddress(User $user): ?AddressData
    {
        $address = Address::query()
            ->where('user_id', $user->getKey())
            ->inPickOrder()
            ->first();

        return $address === null ? null : AddressData::fromModel($address);
    }

    /**
     * @return list<AccountReviewData>
     */
    private function writtenReviews(User $user): array
    {
        return array_values(Review::query()
            ->where('user_id', $user->getKey())
            // The product is rendered as a card, so it needs exactly what a
            // card needs — otherwise this list is an N+1 per review.
            ->with(['product' => fn ($query) => $query->with(['brand:id,name,slug', 'media'])->withReviewStats()])
            ->orderByDesc('id')
            ->take(self::REVIEW_LIMIT)
            ->get()
            ->filter(fn (Review $review): bool => $review->product !== null)
            ->map(fn (Review $review): AccountReviewData => AccountReviewData::fromModel(
                $review,
                ProductCardData::fromModel($review->product),
            ))
            ->all());
    }

    /**
     * The products this shopper looked at, most recent first.
     *
     * Ordered by the view timestamp rather than by product, and re-ordered in
     * PHP after the catalog query, because the catalog query has its own
     * eager loads and joining the history into it would multiply them.
     *
     * @return list<ProductCardData>
     */
    private function recentlyViewedCards(User $user, int $limit): array
    {
        /** @var list<int> $ids */
        $ids = RecentlyViewed::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('viewed_at')
            ->take($limit)
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        $order = array_flip($ids);

        return array_values(Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->published()
            ->visibleInCatalog()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product): int => $order[$product->getKey()] ?? PHP_INT_MAX)
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * The signed-in customer. The route group guarantees one, so this narrows
     * the type rather than making a decision.
     */
    private function customer(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * Home / this page.
     *
     * Two rungs, matching {@see OrderController}:
     * StoreBreadcrumbs reads a null slug past the first rung as the Categories
     * root, so an intermediate "Account" rung would link somewhere it should
     * not.
     *
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

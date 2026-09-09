<?php

namespace App\Http\Controllers\Shop;

use App\Data\BreadcrumbData;
use App\Data\ProductCardData;
use App\Data\ProductDetailData;
use App\Data\ProductPreviewData;
use App\Data\ReviewData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\BuildsCategoryBreadcrumbs;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\RecentlyViewed;
use App\Models\Review;
use App\Support\CategoryTree;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use BuildsCategoryBreadcrumbs, FiltersCatalogProducts;

    /** Products offered in each recommendation rail. */
    private const RAIL_SIZE = 12;

    /**
     * Accessories offered in the prompt that follows an add-to-cart. Far
     * shorter than a rail: this one interrupts the shopper, so it has to be
     * scannable in one glance.
     */
    private const ACCESSORY_LIMIT = 4;

    public function __construct(private Seo $seo) {}

    /** Approved reviews shown before the shopper asks for more. */
    private const REVIEW_LIMIT = 10;

    /** How long a product's recommendation pools stay pinned. */
    private const RECOMMENDATION_TTL_MINUTES = 10;

    /**
     * Show one product.
     *
     * The recommendation pools are randomised, so their ids are picked once and
     * cached: without that they would reshuffle on every partial reload and the
     * rails would visibly rearrange under the shopper.
     *
     * The page has two readers. A shopper only ever reaches a product that is
     * genuinely live. A staff member holding a products permission may also
     * open one that is not — a draft, a scheduled product before its date, an
     * archived or hidden one — because "how will this look on the shop floor"
     * has no honest answer other than the shop floor itself. That second
     * reading is a PREVIEW and says so: see {@see previewFor()}.
     */
    public function show(Request $request, Product $product): Response
    {
        // A catalog-only or search-only product is still a real page; only a
        // fully hidden one — or one that is not live yet — 404s, and then only
        // for a reader who may not preview it.
        $preview = $this->previewFor($request, $product);

        abort_unless($product->isViewableOnStore() || $preview !== null, 404);

        $product->load([
            'brand',
            // ProductDetailData builds a CategoryData from the primary
            // category, which resolves a cover image: without the media here
            // that is a second query on every product page.
            'primaryCategory.media',
            'media',
            'productAttributes' => fn ($query) => $query->visible(),
            'productAttributes.attribute',
        ]);

        if ($product->hasVariants()) {
            $product->load([
                'variants' => fn ($query) => $query->active(),
                'variants.media',
                'variants.attributeValues.attribute',
            ]);
        }

        $product->loadReviewStatsIfMissing();

        $pools = $this->recommendationPools($product);

        // A preview is not a visit. Counting it would put the manager's own
        // proofreading into "customers also viewed" and into their personal
        // recently-viewed list, and the analytics log is the input to a rail
        // shoppers see.
        if ($preview === null) {
            $this->recordView($request, $product);
        }

        $crumbs = $this->breadcrumbs($product);

        return Inertia::render('shop/Product', [
            'product' => ProductDetailData::fromModel($product, $crumbs),
            // Null on the live page, and that is what makes the preview
            // treatment impossible to leak: the banner, the suppressed buy
            // controls and the noindex head all hang off this one prop, which
            // a shopper never receives.
            'preview' => $preview,
            // Overrides the default shared by HandleInertiaRequests. The
            // canonical prefers the product's own column so two URLs for the
            // same item — a variant link, a campaign parameter — concede to one.
            //
            // A preview answers `noindex, nofollow` whatever the store's own
            // setting says, and offers no structured data at all. It is behind
            // a login and a permission so no crawler should reach it, but a
            // page that is not for sale must not describe itself to one as an
            // Offer if something ever does.
            'documentHead' => $this->seo->page(
                title: $product->meta_title ?? $product->name,
                description: $product->meta_description ?? $product->short_description,
                canonicalUrl: $product->canonical_url ?: route('product.show', $product->slug),
                jsonLd: $preview !== null ? [] : [
                    $this->seo->product($product),
                    $this->seo->breadcrumbs($crumbs),
                ],
                robots: $preview !== null ? Seo::NOINDEX : null,
            ),
            'accessories' => $this->accessories($product),
            'related' => $this->rail($pools['related']),
            'brandProducts' => $this->rail($pools['brand']),
            'alsoViewed' => $this->rail($pools['alsoViewed']),
            'reviews' => Inertia::defer(fn (): array => $this->reviews($product)),
        ]);
    }

    /**
     * The preview badge for this reader, or null.
     *
     * Null for a product that is genuinely live — the storefront page is then
     * the real page and must read identically to staff and shoppers, or staff
     * are proofreading something nobody else will see.
     *
     * Null too for anyone who does not hold a products permission, which is
     * what keeps the URL a 404 for the whole world. There is deliberately no
     * token and no signed URL here: the reader is already authenticated, and
     * `products.view` is already the permission that says "this person is
     * trusted with products that are not on sale yet" — the admin table it
     * guards lists every draft in the catalog by name. A second mechanism
     * would be a second thing to leak and a second thing to revoke.
     *
     * A product in the bin is not previewable at all. Route model binding
     * resolves against the default scope, so it 404s before this is asked —
     * and rightly: a binned product is not "not public yet", it is withdrawn.
     */
    private function previewFor(Request $request, Product $product): ?ProductPreviewData
    {
        if ($product->isViewableOnStore()) {
            return null;
        }

        $user = $request->user();

        if ($user === null || ! $user->canAny(['products.view', 'products.manage'])) {
            return null;
        }

        return ProductPreviewData::fromModel($product);
    }

    /**
     * Log the view for both the signed-in user's own history and the store-wide
     * analytics log that "customers also viewed" is built from.
     *
     * Deferred: these are two writes on the read path of the most-requested
     * page in the store, and nothing in the response depends on them. A dropped
     * view costs a row in an analytics log, which does not warrant a queued job
     * with its retries and durability. The session id is read here, in the
     * request, because the deferred callback runs after the response.
     */
    private function recordView(Request $request, Product $product): void
    {
        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        defer(function () use ($product, $user, $sessionId): void {
            if ($user !== null) {
                RecentlyViewed::record($user, $product);
            }

            ProductView::record($product, $user, $sessionId);
        });
    }

    /**
     * The three recommendation id pools, resolved together and pinned for a few
     * minutes so partial reloads of this page keep the same rails.
     *
     * @return array{related: list<int>, brand: list<int>, alsoViewed: list<int>}
     */
    private function recommendationPools(Product $product): array
    {
        /** @var array{related: list<int>, brand: list<int>, alsoViewed: list<int>} $pools */
        $pools = Cache::remember(
            "product-recommendations:{$product->getKey()}",
            now()->addMinutes(self::RECOMMENDATION_TTL_MINUTES),
            fn (): array => [
                'related' => $this->relatedIds($product),
                'brand' => $this->sameBrandIds($product),
                'alsoViewed' => $this->alsoViewedIds($product),
            ],
        );

        return $pools;
    }

    /**
     * @return list<int>
     */
    private function relatedIds(Product $product): array
    {
        if ($product->primary_category_id === null) {
            return [];
        }

        return array_values($this->recommendableQuery($product)
            ->where('primary_category_id', $product->primary_category_id)
            ->inRandomOrder()
            ->take(self::RAIL_SIZE)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * @return list<int>
     */
    private function sameBrandIds(Product $product): array
    {
        if ($product->brand_id === null) {
            return [];
        }

        return array_values($this->recommendableQuery($product)
            ->where('brand_id', $product->brand_id)
            ->inRandomOrder()
            ->take(self::RAIL_SIZE)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Products seen in the same browsing session as this one, most co-viewed
     * first. A self-join on the analytics log rather than on order history, so
     * it works for guests and for products nobody has bought yet.
     *
     * @return list<int>
     */
    private function alsoViewedIds(Product $product): array
    {
        /** @var list<int> $ids */
        $ids = DB::table('product_views as viewed_this')
            ->join('product_views as viewed_also', function ($join) use ($product): void {
                $join->on('viewed_this.session_id', '=', 'viewed_also.session_id')
                    ->where('viewed_also.product_id', '!=', $product->getKey());
            })
            ->where('viewed_this.product_id', $product->getKey())
            ->whereNotNull('viewed_this.session_id')
            ->groupBy('viewed_also.product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::RAIL_SIZE)
            ->pluck('viewed_also.product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * A rail only offers stock a shopper can act on, and never the product they
     * are already looking at.
     *
     * @return Builder<Product>
     */
    private function recommendableQuery(Product $product): Builder
    {
        return Product::query()
            ->published()
            ->visibleInCatalog()
            ->inStockAndPriced()
            ->whereKeyNot($product->getKey());
    }

    /**
     * The accessories curated for this product, for the prompt that follows a
     * successful add-to-cart.
     *
     * Composed onto the model's own `accessories` relation, so the curator's
     * ordering and the accessory link type are stated in one place — the join
     * carries the gate and the cap, which is what keeps a hidden or sold-out
     * accessory from silently eating one of the four slots. An empty list is
     * the signal the client uses to leave the prompt shut altogether.
     *
     * @return list<ProductCardData>
     */
    private function accessories(Product $product): array
    {
        return array_values($product->accessories()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->published()
            ->visibleInCatalog()
            ->inStockAndPriced()
            ->take(self::ACCESSORY_LIMIT)
            ->get()
            ->map(fn (Product $accessory): ProductCardData => ProductCardData::fromModel($accessory))
            ->all());
    }

    /**
     * Hydrate a pinned id pool, preserving the order the ids were picked in.
     *
     * @param  list<int>  $ids
     * @return list<ProductCardData>
     */
    private function rail(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $order = array_flip($ids);

        return array_values($this->catalogQuery()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product): int => $order[$product->getKey()] ?? PHP_INT_MAX)
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * @return list<ReviewData>
     */
    private function reviews(Product $product): array
    {
        return array_values(Review::query()
            ->approved()
            ->forProduct($product)
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->take(self::REVIEW_LIMIT)
            ->get()
            ->map(fn (Review $review): ReviewData => ReviewData::fromModel($review))
            ->all());
    }

    /**
     * Home / Categories / the product's category trail / the product.
     *
     * The tree is only loaded when the product is actually filed somewhere —
     * an unfiled product's trail stops at Categories and needs no walk.
     *
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(Product $product): array
    {
        $category = $product->primaryCategory;

        $trail = $this->categoryBreadcrumbs(
            $category,
            $category === null ? CategoryTree::empty() : CategoryTree::load(),
        );

        return [...$trail, new BreadcrumbData(name: $product->name, slug: $product->slug)];
    }
}

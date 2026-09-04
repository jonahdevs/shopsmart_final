<?php

namespace App\Http\Controllers\Shop;

use App\Data\BreadcrumbData;
use App\Data\ProductCardData;
use App\Data\ProductDetailData;
use App\Data\ReviewData;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\RecentlyViewed;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use FiltersCatalogProducts;

    /** Products offered in each recommendation rail. */
    private const RAIL_SIZE = 12;

    /** Approved reviews shown before the shopper asks for more. */
    private const REVIEW_LIMIT = 10;

    /** How long a product's recommendation pools stay pinned. */
    private const RECOMMENDATION_TTL_MINUTES = 10;

    /** Guards the breadcrumb walk against a cyclic category tree. */
    private const MAX_TREE_DEPTH = 10;

    /**
     * Show one product.
     *
     * The recommendation pools are randomised, so their ids are picked once and
     * cached: without that they would reshuffle on every partial reload and the
     * rails would visibly rearrange under the shopper.
     */
    public function show(Request $request, Product $product): Response
    {
        // A catalog-only or search-only product is still a real page; only a
        // fully hidden one — or one that is not live yet — 404s.
        abort_unless($product->isPublished() && $product->visibility !== ProductVisibility::Hidden, 404);

        $product->load([
            'brand',
            'primaryCategory',
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

        $this->recordView($request, $product);

        return Inertia::render('shop/Product', [
            'product' => ProductDetailData::fromModel($product, $this->breadcrumbs($product)),
            'related' => $this->rail($pools['related']),
            'brandProducts' => $this->rail($pools['brand']),
            'alsoViewed' => $this->rail($pools['alsoViewed']),
            'reviews' => Inertia::defer(fn (): array => $this->reviews($product)),
        ]);
    }

    /**
     * Log the view for both the signed-in user's own history and the store-wide
     * analytics log that "customers also viewed" is built from.
     */
    private function recordView(Request $request, Product $product): void
    {
        $user = $request->user();

        if ($user !== null) {
            RecentlyViewed::record($user, $product);
        }

        ProductView::record($product, $user, $request->hasSession() ? $request->session()->getId() : null);
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
            ->where('stock_status', StockStatus::InStock)
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->whereKeyNot($product->getKey());
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
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(Product $product): array
    {
        $trail = [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: __('Categories'), slug: null),
        ];

        $category = $product->primaryCategory;

        if ($category === null) {
            return [...$trail, new BreadcrumbData(name: $product->name, slug: $product->slug)];
        }

        // One query for the whole edge list beats one query per level up the tree.
        $parentByChild = Category::query()
            ->whereNotNull('parent_id')
            ->pluck('parent_id', 'id');

        $ancestors = [$category->getKey()];
        $current = $parentByChild->get($category->getKey());
        $depth = 0;

        while ($current !== null && $depth < self::MAX_TREE_DEPTH) {
            $ancestors[] = (int) $current;
            $current = $parentByChild->get((int) $current);
            $depth++;
        }

        $named = Category::query()
            ->whereIn('id', $ancestors)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        foreach (array_reverse($ancestors) as $id) {
            $ancestor = $named->get($id);

            if ($ancestor !== null) {
                $trail[] = new BreadcrumbData(name: $ancestor->name, slug: $ancestor->slug);
            }
        }

        $trail[] = new BreadcrumbData(name: $product->name, slug: $product->slug);

        return $trail;
    }
}

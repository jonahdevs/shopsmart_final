<?php

namespace App\Http\Controllers\Shop\Concerns;

use App\Data\CatalogFilterData;
use App\Data\FacetOptionData;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Support\Money;
use App\Support\StorefrontCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The faceted-listing engine shared by the catalog and the category page.
 *
 * Both pages read the same query string, run the same filters and sorts, and
 * publish the same facet shape; the only difference is that the category page
 * pins the listing to one subtree.
 */
trait FiltersCatalogProducts
{
    /** Products per page; "load more" appends another page of the same size. */
    private const PER_PAGE = 24;

    /** Upper bound of the price slider, in whole KES. */
    private const PRICE_CEILING = 6_000_000;

    /** How recently a product must have been published to count as a new arrival. */
    private const NEW_ARRIVAL_WINDOW_DAYS = 60;

    /** Merchandising tag that pins a product into "new arrivals" regardless of age. */
    private const NEW_ARRIVAL_TAG = 'New Arrival';

    /** How long the category facet counts stay fresh. */
    private const COUNTS_FRESH_SECONDS = 300;

    /** How long they may still be served stale while a refresh runs behind the response. */
    private const COUNTS_STALE_SECONDS = 900;

    /**
     * The base listing query: live, catalog-visible products with everything a
     * product card renders already loaded.
     *
     * @return Builder<Product>
     */
    protected function catalogQuery(): Builder
    {
        return Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->published()
            ->visibleInCatalog()
            ->honorStockVisibility();
    }

    /**
     * Read the validated query string into the listing's filter state.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function filtersFrom(array $validated): CatalogFilterData
    {
        /** @var list<string> $categories */
        $categories = array_values(array_filter(array_map('strval', (array) ($validated['cat'] ?? []))));

        /** @var list<int> $brands */
        $brands = array_values(array_map('intval', (array) ($validated['brand'] ?? [])));

        $priceMin = (int) ($validated['pmin'] ?? 0);
        $priceMax = (int) ($validated['pmax'] ?? self::PRICE_CEILING);
        $inStockOnly = (bool) ($validated['stock'] ?? false);
        $minRating = (int) ($validated['rating'] ?? 0);
        $tag = trim((string) ($validated['tag'] ?? ''));
        $newArrivalsOnly = (bool) ($validated['arrivals'] ?? false);
        $term = trim((string) ($validated['q'] ?? ''));

        return new CatalogFilterData(
            q: $term,
            categories: $categories,
            brands: $brands,
            priceMin: $priceMin,
            priceMax: $priceMax,
            inStockOnly: $inStockOnly,
            minRating: $minRating,
            tag: $tag,
            newArrivalsOnly: $newArrivalsOnly,
            sort: (string) ($validated['sort'] ?? 'popularity'),
            hasActiveFilters: $categories !== []
                || $brands !== []
                || $inStockOnly
                || $minRating > 0
                || $priceMin > 0
                || $priceMax < self::PRICE_CEILING
                || $tag !== ''
                || $newArrivalsOnly
                || $term !== '',
        );
    }

    /**
     * Apply every filter except the category scope, which each page owns.
     *
     * @param  Builder<Product>  $query
     */
    protected function applyFilters(Builder $query, CatalogFilterData $filters): void
    {
        if ($filters->q !== '') {
            $this->applySearchTerm($query, $filters->q);
        }

        if ($filters->brands !== []) {
            $query->whereIn('brand_id', $filters->brands);
        }

        if ($filters->tag !== '') {
            $query->whereHas('tags', fn (Builder $tag) => $tag->where('name->'.app()->getLocale(), $filters->tag));
        }

        if ($filters->newArrivalsOnly) {
            $this->applyNewArrivals($query);
        }

        if ($filters->inStockOnly) {
            $query->where('stock_status', StockStatus::InStock);
        }

        if ($filters->minRating > 0) {
            $query->whereIn('id', Review::query()
                ->approved()
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('AVG(rating) >= ?', [$filters->minRating]));
        }

        $this->applyPriceRange($query, $filters);
        $this->applySort($query, $filters->sort);
    }

    /**
     * Free-text match across the columns a shopper would type, plus the brand
     * name — which lives on another table, so the Scout database engine (which
     * only knows real columns on `products`) cannot cover it.
     *
     * @param  Builder<Product>  $query
     */
    private function applySearchTerm(Builder $query, string $term): void
    {
        $query->where(function (Builder $match) use ($term): void {
            $match
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('model_number', 'like', "%{$term}%")
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Sellable stock published inside the window, or pinned there by the
     * "New Arrival" tag regardless of age.
     *
     * @param  Builder<Product>  $query
     */
    private function applyNewArrivals(Builder $query): void
    {
        $query
            ->where('stock_status', StockStatus::InStock)
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->where(fn (Builder $recent) => $recent
                ->where('published_at', '>=', now()->subDays(self::NEW_ARRIVAL_WINDOW_DAYS))
                ->orWhereHas('tags', fn (Builder $tag) => $tag->where('name->'.app()->getLocale(), self::NEW_ARRIVAL_TAG)));
    }

    /**
     * The slider works in whole KES; the columns are cents. Unpriced products
     * survive the upper bound (they are quote-on-request, not expensive) but
     * drop out as soon as the shopper sets a floor.
     *
     * @param  Builder<Product>  $query
     */
    private function applyPriceRange(Builder $query, CatalogFilterData $filters): void
    {
        $money = app(Money::class);

        $query->where(fn (Builder $ceiling) => $ceiling
            ->whereNull('price')
            ->orWhere('price', '<=', $money->toMinor($filters->priceMax)));

        if ($filters->priceMin > 0) {
            $query->whereNotNull('price')->where('price', '>=', $money->toMinor($filters->priceMin));
        }
    }

    /**
     * Price sorts run on the price actually charged, so a discounted product
     * sorts where the shopper sees it. Unpriced products always sink to the
     * bottom rather than clustering at whichever end NULL happens to sort.
     *
     * Every sort ends on `id`. None of these columns is unique, and the grid
     * pages through "load more" — without a unique tie-breaker the database is
     * free to order tied rows differently between one page and the next, which
     * shows up as a tile repeated across the join or missing entirely.
     *
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price-asc' => $query->orderByRaw('COALESCE(sale_price, price) IS NULL, COALESCE(sale_price, price) ASC')->orderByDesc('id'),
            'price-desc' => $query->orderByRaw('COALESCE(sale_price, price) IS NULL, COALESCE(sale_price, price) DESC')->orderByDesc('id'),
            'name-asc' => $query->orderBy('name')->orderByDesc('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            // "Popularity" is merchandised by hand through sort_order, newest first within a rank.
            default => $query->orderBy('sort_order')->orderByDesc('id'),
        };
    }

    /**
     * Pin a listing to a set of categories. A product belongs to a category
     * either as its primary category or through the pivot, and the seeder keeps
     * both in step — but only checking one would silently drop imports that set
     * just the other.
     *
     * @param  Builder<Product>  $query
     * @param  list<int>  $categoryIds
     */
    protected function scopeToCategories(Builder $query, array $categoryIds): void
    {
        $query->where(fn (Builder $inCategory) => $inCategory
            ->whereIn('primary_category_id', $categoryIds)
            ->orWhereHas('categories', fn (Builder $pivot) => $pivot->whereIn('categories.id', $categoryIds)));
    }

    /**
     * @param  Builder<Product>  $query
     * @return LengthAwarePaginator<int, Product>
     */
    protected function paginateCatalog(Builder $query): LengthAwarePaginator
    {
        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * How many live catalog products sit directly in each category, keyed by
     * category id.
     *
     * One query: the two membership sources are UNIONed (which de-duplicates)
     * and counted in the database, rather than paying a correlated subquery per
     * category row or a count query per facet.
     *
     * Cached because it scans the whole catalog, depends on nothing in the
     * request, and is read by four pages. ProductObserver and CategoryObserver
     * clear the key when something that changes a total is written, so the
     * window below is a backstop for edits made outside Eloquent. `flexible()`
     * serves the stale value and refreshes after the response rather than
     * making one unlucky shopper wait for the rebuild.
     *
     * @return array<int, int>
     */
    protected function catalogCountsByCategory(): array
    {
        /** @var array<int, int> $cached */
        $cached = Cache::flexible(
            StorefrontCache::CATEGORY_PRODUCT_COUNTS,
            [self::COUNTS_FRESH_SECONDS, self::COUNTS_STALE_SECONDS],
            fn (): array => $this->freshCatalogCountsByCategory(),
        );

        return $cached;
    }

    /**
     * @return array<int, int>
     */
    private function freshCatalogCountsByCategory(): array
    {
        $liveProducts = Product::query()
            ->published()
            ->visibleInCatalog()
            ->honorStockVisibility()
            ->select('products.id');

        $primary = DB::table('products')
            ->select(['id as product_id', 'primary_category_id as category_id'])
            ->whereNotNull('primary_category_id')
            ->whereIn('id', $liveProducts);

        $membership = DB::table('category_product')
            ->select(['product_id', 'category_id'])
            ->whereIn('product_id', $liveProducts)
            ->union($primary);

        /** @var array<int, int> $counts */
        $counts = DB::query()
            ->fromSub($membership, 'membership')
            ->select('category_id')
            ->selectRaw('COUNT(DISTINCT product_id) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }

    /**
     * Active categories that actually hold live catalog products, as sidebar
     * facets. Categories with no products are dropped — ticking such a box
     * could only ever empty the grid.
     *
     * @param  array<int, int>  $counts  category id => product count
     * @param  list<int>|null  $restrictToIds  Narrow the facets to a subtree.
     * @return list<FacetOptionData>
     */
    protected function categoryFacets(array $counts, ?array $restrictToIds = null): array
    {
        $ids = array_keys(array_filter($counts, fn (int $count): bool => $count > 0));

        if ($restrictToIds !== null) {
            $ids = array_values(array_intersect($ids, $restrictToIds));
        }

        if ($ids === []) {
            return [];
        }

        return array_values(Category::query()
            ->active()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category): FacetOptionData => new FacetOptionData(
                id: $category->getKey(),
                name: $category->name,
                slug: $category->slug,
                count: $counts[$category->getKey()] ?? 0,
            ))
            ->all());
    }

    /**
     * Brands with at least one live catalog product, optionally narrowed to a
     * category subtree. Counted in a single grouped query.
     *
     * @param  list<int>|null  $categoryIds
     * @return list<FacetOptionData>
     */
    protected function brandFacets(?array $categoryIds = null): array
    {
        $counts = $this->brandCounts($categoryIds);

        if ($counts === []) {
            return [];
        }

        return array_values(Brand::query()
            ->active()
            ->whereIn('id', array_keys($counts))
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Brand $brand): FacetOptionData => new FacetOptionData(
                id: $brand->getKey(),
                name: $brand->name,
                slug: $brand->slug,
                count: $counts[$brand->getKey()] ?? 0,
            ))
            ->all());
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return array<int, int>
     */
    private function brandCounts(?array $categoryIds): array
    {
        $query = Product::query()
            ->published()
            ->visibleInCatalog()
            ->honorStockVisibility()
            ->whereNotNull('brand_id');

        if ($categoryIds !== null) {
            $this->scopeToCategories($query, $categoryIds);
        }

        /** @var array<int, int> $counts */
        $counts = $query
            ->select('brand_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('brand_id')
            ->pluck('total', 'brand_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }
}

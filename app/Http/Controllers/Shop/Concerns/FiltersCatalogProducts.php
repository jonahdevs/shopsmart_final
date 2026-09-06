<?php

namespace App\Http\Controllers\Shop\Concerns;

use App\Data\CatalogFilterData;
use App\Data\FacetOptionData;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Support\CategoryTree;
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
        return $this->decorateForCards($this->liveCatalogProducts());
    }

    /**
     * The same base for the search results page, which obeys `visibleInSearch()`
     * rather than `visibleInCatalog()`.
     *
     * The distinction is the whole point of the two visibility flags: a
     * search-only product has a real product page and must be reachable from a
     * results list, while a catalog-only one must not be — otherwise the
     * listing offers a tile whose page answers 404.
     *
     * @return Builder<Product>
     */
    protected function searchQuery(): Builder
    {
        return $this->decorateForCards($this->liveSearchProducts());
    }

    /**
     * Live, catalog-visible products, with nothing loaded.
     *
     * Kept separate from {@see self::catalogQuery()} because the facet
     * aggregates group and count over this set: the eager loads and the review
     * stat subqueries below are exactly what a `GROUP BY` cannot carry.
     *
     * @return Builder<Product>
     */
    protected function liveCatalogProducts(): Builder
    {
        return Product::query()
            ->published()
            ->visibleInCatalog()
            ->honorStockVisibility();
    }

    /**
     * Live, search-visible products, with nothing loaded. The same definition
     * the header autocomplete applies inside Scout's `query` callback.
     *
     * @return Builder<Product>
     */
    protected function liveSearchProducts(): Builder
    {
        return Product::query()
            ->published()
            ->visibleInSearch()
            ->honorStockVisibility();
    }

    /**
     * Load everything a product card renders, so a grid of tiles costs the same
     * two eager loads however many tiles it holds.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function decorateForCards(Builder $query): Builder
    {
        return $query
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats();
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
        // Null, not the ceiling: a defaulted upper bound is a filter nobody
        // asked for, and it silently hid every product priced above it.
        $priceMax = isset($validated['pmax']) ? (int) $validated['pmax'] : null;
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
            priceCeiling: self::PRICE_CEILING,
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
                || $priceMax !== null
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
     * The product columns a shopper's term is matched against.
     *
     * A hook rather than a constant: the search results page widens this to the
     * short description so that everything Scout offers in the header dropdown
     * — whose `toSearchableArray()` indexes that column — is also findable on
     * the page the dropdown links through to.
     *
     * @return list<'name'|'sku'|'model_number'|'short_description'>
     */
    protected function searchTermColumns(): array
    {
        return ['name', 'sku', 'model_number'];
    }

    /**
     * Free-text match across the columns a shopper would type, plus the brand
     * name — which lives on another table, so the Scout database engine (which
     * only knows real columns on `products`) cannot cover it.
     *
     * @param  Builder<Product>  $query
     */
    protected function applySearchTerm(Builder $query, string $term): void
    {
        $pattern = $this->containsPattern($term);
        $columns = $this->searchTermColumns();

        $query->where(function (Builder $match) use ($pattern, $columns): void {
            foreach ($columns as $column) {
                $match->orWhereRaw($this->likeExpression($column), [$pattern]);
            }

            $match->orWhereHas('brand', fn (Builder $brand) => $brand->whereRaw($this->likeExpression('name'), [$pattern]));
        });
    }

    /**
     * The escape character for every LIKE this application builds.
     *
     * Deliberately not a backslash. MySQL treats `\` as LIKE's default escape
     * while SQLite has none, so a backslash-escaped wildcard matches itself on
     * one driver and nothing at all on the other — and the suite runs on
     * SQLite while production runs on MySQL. Naming the character explicitly
     * makes both dialects agree.
     */
    private const LIKE_ESCAPE = '!';

    /**
     * A `column LIKE ? ESCAPE '!'` fragment.
     *
     * The column is constrained to a literal from a closed set rather than an
     * arbitrary string, so the result is a `literal-string` and PHPStan's
     * guard against assembling SQL out of runtime values still holds. The
     * pattern itself is bound.
     *
     * @param  'name'|'sku'|'model_number'|'short_description'  $column
     * @return literal-string
     */
    protected function likeExpression(string $column): string
    {
        return $column." LIKE ? ESCAPE '".self::LIKE_ESCAPE."'";
    }

    /**
     * Wrap a shopper's term as a LIKE "contains" pattern.
     *
     * The term is bound, so the wildcards were never an injection risk — they
     * were a correctness one: `?q=%` matched the entire catalog and `a_b`
     * matched "axb". Escaped against {@see self::LIKE_ESCAPE}, a typed `%` or
     * `_` matches itself on both drivers — so searching "100%" finds the
     * product actually called "100% Cotton".
     */
    protected function containsPattern(string $term): string
    {
        $escape = self::LIKE_ESCAPE;

        // The escape character itself has to be escaped first, or escaping the
        // wildcards would produce sequences this pass then re-escapes.
        $escaped = str_replace(
            [$escape, '%', '_'],
            [$escape.$escape, $escape.'%', $escape.'_'],
            $term,
        );

        return '%'.$escaped.'%';
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
     * Only a bound the shopper actually supplied is applied. Defaulting the
     * ceiling to PRICE_CEILING put `price <= 600_000_000` on every listing
     * query, so anything dearer than the slider's top stop vanished from the
     * whole storefront with nothing on the page to say a filter was on.
     *
     * @param  Builder<Product>  $query
     */
    private function applyPriceRange(Builder $query, CatalogFilterData $filters): void
    {
        $money = app(Money::class);

        if ($filters->priceMax !== null) {
            $query->where(fn (Builder $ceiling) => $ceiling
                ->whereNull('price')
                ->orWhere('price', '<=', $money->toMinor($filters->priceMax)));
        }

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
     * Resolve ticked category slugs to ids, each expanded through its whole
     * subtree. A top-level category holds no products of its own, so resolving
     * the slug alone returns an empty grid for a box the sidebar itself offers.
     *
     * An unknown slug resolves to nothing, which correctly empties the grid
     * rather than being ignored.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    protected function expandedCategoryIds(array $slugs, CategoryTree $tree): array
    {
        $expanded = [];

        foreach (Category::query()->whereIn('slug', $slugs)->pluck('id') as $id) {
            foreach ($tree->subtreeIds((int) $id) as $descendantId) {
                $expanded[$descendantId] = true;
            }
        }

        return array_keys($expanded);
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
     * The page size every listing shares, for a caller that has to describe a
     * page it never ran a query for.
     */
    protected function perPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * The ids of the live catalog products filed directly in each category,
     * keyed by category id.
     *
     * Ids rather than a tally, because every consumer rolls these up a subtree
     * and a product may be filed in a parent and one of its children at once.
     * Summing tallies counted it twice, so the facet promised more tiles than
     * ticking it returned; a union of ids counts it once.
     *
     * One query: the two membership sources are UNIONed (which de-duplicates)
     * rather than paying a correlated subquery per category row or a count
     * query per facet.
     *
     * Cached because it scans the whole catalog, depends on nothing in the
     * request, and is read by four pages. ProductObserver and CategoryObserver
     * clear the key when something that changes a total is written, so the
     * window below is a backstop for edits made outside Eloquent. `flexible()`
     * serves the stale value and refreshes after the response rather than
     * making one unlucky shopper wait for the rebuild.
     *
     * @return array<int, list<int>> category id => live product ids
     */
    protected function catalogProductIdsByCategory(): array
    {
        /** @var array<int, list<int>> $idsByCategory */
        $idsByCategory = Cache::flexible(
            StorefrontCache::CATEGORY_PRODUCT_IDS,
            [self::COUNTS_FRESH_SECONDS, self::COUNTS_STALE_SECONDS],
            fn (): array => $this->freshCatalogProductIdsByCategory(),
        );

        return $idsByCategory;
    }

    /**
     * @return array<int, list<int>>
     */
    private function freshCatalogProductIdsByCategory(): array
    {
        return $this->productIdsByCategoryFor($this->liveCatalogProducts());
    }

    /**
     * Roll an arbitrary set of products up into "product ids per category".
     *
     * Factored out of the cached catalog map so the search results page can
     * build the same shape over the products a term actually matched. Its
     * facets are therefore counted against the result set rather than against
     * the whole catalog, which is what keeps the promise a facet number makes.
     *
     * @param  Builder<Product>  $products
     * @return array<int, list<int>>
     */
    protected function productIdsByCategoryFor(Builder $products): array
    {
        $liveProducts = $products->clone()->select('products.id');

        $primary = DB::table('products')
            ->select(['id as product_id', 'primary_category_id as category_id'])
            ->whereNotNull('primary_category_id')
            ->whereIn('id', $liveProducts);

        $membership = DB::table('category_product')
            ->select(['product_id', 'category_id'])
            ->whereIn('product_id', $liveProducts)
            ->union($primary);

        /** @var array<int, array<int, true>> $seen */
        $seen = [];

        foreach (DB::query()->fromSub($membership, 'membership')->get() as $row) {
            $seen[(int) $row->category_id][(int) $row->product_id] = true;
        }

        /** @var array<int, list<int>> $idsByCategory */
        $idsByCategory = [];

        foreach ($seen as $categoryId => $productIds) {
            $idsByCategory[$categoryId] = array_keys($productIds);
        }

        return $idsByCategory;
    }

    /**
     * Active categories whose subtree holds live catalog products, as sidebar
     * facets. A category with an empty subtree is dropped — ticking such a box
     * could only ever empty the grid.
     *
     * Counts are rolled up the subtree because ticking the box scopes the
     * listing to the subtree: a top-level category holds no products of its
     * own, so a direct tally would either hide it from the sidebar or advertise
     * zero next to a box that returns fifty.
     *
     * @param  array<int, list<int>>  $productIdsByCategory  category id => live product ids
     * @return list<FacetOptionData>
     */
    protected function categoryFacets(array $productIdsByCategory, CategoryTree $tree): array
    {
        $ids = [];

        foreach (array_keys($productIdsByCategory) as $categoryId) {
            $ids[$categoryId] = true;

            foreach ($tree->ancestorIds($categoryId) as $ancestorId) {
                $ids[$ancestorId] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $facets = [];

        $categories = Category::query()
            ->active()
            ->whereIn('id', array_keys($ids))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        foreach ($categories as $category) {
            $count = $tree->subtreeCount($category->getKey(), $productIdsByCategory);

            if ($count === 0) {
                continue;
            }

            $facets[] = new FacetOptionData(
                id: $category->getKey(),
                name: $category->name,
                slug: $category->slug,
                count: $count,
            );
        }

        return $facets;
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
        $products = $this->liveCatalogProducts();

        if ($categoryIds !== null) {
            $this->scopeToCategories($products, $categoryIds);
        }

        return $this->brandFacetsFor($products);
    }

    /**
     * The same brand facets counted over an arbitrary set of products, so the
     * search page can count them over what the term matched.
     *
     * @param  Builder<Product>  $products
     * @return list<FacetOptionData>
     */
    protected function brandFacetsFor(Builder $products): array
    {
        $counts = $this->brandCounts($products);

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
     * @param  Builder<Product>  $products
     * @return array<int, int>
     */
    private function brandCounts(Builder $products): array
    {
        /** @var array<int, int> $counts */
        $counts = $products->clone()
            ->whereNotNull('brand_id')
            ->select('brand_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('brand_id')
            ->pluck('total', 'brand_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }
}

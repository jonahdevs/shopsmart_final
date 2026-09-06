<?php

namespace App\Http\Controllers\Shop;

use App\Data\FacetOptionData;
use App\Data\ProductCardData;
use App\Data\ProductListData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Http\Requests\Shop\SearchSuggestRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use FiltersCatalogProducts;

    /** Products offered in the header dropdown. */
    private const PRODUCT_LIMIT = 6;

    /** Category and brand shortcuts offered alongside them. */
    private const TAXONOMY_LIMIT = 4;

    /**
     * The shortest term either endpoint will run.
     *
     * The dropdown enforces it through {@see SearchSuggestRequest}, which
     * rejects anything shorter outright — it is a JSON endpoint firing on every
     * keystroke, and a 422 is the cheapest possible answer. The results page
     * cannot do that: it is a page a shopper can land on with an empty or
     * half-typed box, and answering a navigation with a validation error would
     * bounce them somewhere they did not ask to go. It renders a prompt instead.
     */
    private const MINIMUM_TERM_LENGTH = 2;

    /**
     * Show the full search results page.
     *
     * Deliberately the catalog listing with a term on it: the same filters,
     * facets, sorts and paging, so a shopper who narrows a search gets the
     * controls they already learned on the catalog rather than a second,
     * weaker set. The two differences are both real:
     *
     * - the base query obeys `visibleInSearch()` rather than
     *   `visibleInCatalog()`, matching what the dropdown offers, so a result
     *   can never be a tile whose product page answers 404;
     * - the facets are counted over the products the term matched rather than
     *   over the whole catalog, so ticking a box returns the number beside it.
     *
     * The term goes through the catalog's own escaped LIKE rather than through
     * Scout. Scout's database engine interpolates the raw term into its
     * pattern, so a shopper typing `%` would be handed the entire shop; and it
     * only knows real columns on `products`, so it cannot match a brand name.
     * {@see self::searchTermColumns()} widens the column list to cover
     * everything `Product::toSearchableArray()` indexes, so nothing the
     * dropdown offers is missing from the page it links through to.
     */
    public function index(CatalogFilterRequest $request): Response
    {
        $filters = $this->filtersFrom($request->validated());

        if (mb_strlen($filters->q) < self::MINIMUM_TERM_LENGTH) {
            return Inertia::render('shop/Search', [
                'products' => ProductListData::emptyPage($this->perPage()),
                'filters' => $filters,
                'categoryFacets' => [],
                'brandFacets' => [],
                'searched' => false,
                'minimumTermLength' => self::MINIMUM_TERM_LENGTH,
            ]);
        }

        $tree = CategoryTree::load();

        $query = $this->searchQuery();

        if ($filters->categories !== []) {
            $this->scopeToCategories($query, $this->expandedCategoryIds($filters->categories, $tree));
        }

        // The term is one of the filters, so this applies it along with the
        // brand, price, rating and stock boxes the sidebar offers.
        $this->applyFilters($query, $filters);

        return Inertia::render('shop/Search', [
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->categoryFacets($this->productIdsByCategoryFor($this->matchedProducts($filters->q)), $tree),
            'brandFacets' => $this->brandFacetsFor($this->matchedProducts($filters->q)),
            'searched' => true,
            'minimumTermLength' => self::MINIMUM_TERM_LENGTH,
        ]);
    }

    /**
     * Everything the term matched, before any facet narrows it.
     *
     * A fresh builder each call: the two facet aggregates group over it, and a
     * builder that has already had `GROUP BY` put on it cannot be reused.
     *
     * @return Builder<Product>
     */
    private function matchedProducts(string $term): Builder
    {
        $query = $this->liveSearchProducts();

        $this->applySearchTerm($query, $term);

        return $query;
    }

    /**
     * The short description joins the match here, and only here.
     *
     * Scout indexes it, so the header dropdown can offer a product whose name
     * does not contain the term at all. Without it in this list the shopper
     * would click "see all results" and find the row they had just been shown
     * missing from the page.
     *
     * @return list<'name'|'sku'|'model_number'|'short_description'>
     */
    protected function searchTermColumns(): array
    {
        return ['name', 'sku', 'model_number', 'short_description'];
    }

    /**
     * Answer the header's autocomplete.
     *
     * Matched exactly the way {@see self::index()} matches, so the six rows the
     * dropdown offers are the first six rows of the page behind it. They used
     * to be found two different ways and could disagree.
     *
     * Categories and brands stay plain lookups — a handful of rows, not worth
     * indexing. The two-character minimum lives in the form request, so a single
     * keystroke never reaches the database at all.
     */
    public function suggest(SearchSuggestRequest $request): JsonResponse
    {
        /** @var string $term */
        $term = $request->validated('q');

        return response()->json([
            'query' => $term,
            'products' => $this->products($term),
            'categories' => $this->categories($term),
            'brands' => $this->brands($term),
        ]);
    }

    /**
     * @return list<ProductCardData>
     */
    private function products(string $term): array
    {
        $query = $this->liveSearchProducts()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats();

        $this->applySearchTerm($query, $term);

        return array_values($query
            ->take(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * Live, search-visible products — the definition a brand shortcut has to
     * agree with, or the header would offer a brand whose results page is empty.
     *
     * Narrower than {@see self::liveSearchProducts()} on purpose: this one is
     * used inside `whereHas`, where the stock-visibility scope would be applied
     * to the wrong table.
     *
     * @param  Builder<Product>  $query
     */
    private function constrainToSearchableProducts(Builder $query): void
    {
        $query->published()->visibleInSearch();
    }

    /**
     * The dropdown renders these as shortcuts, not as facets, so `count` is
     * left at zero exactly as it is for brands. Populating it would mean
     * running the store-wide category count aggregate on every keystroke to
     * decorate at most four rows.
     *
     * @return list<FacetOptionData>
     */
    private function categories(string $term): array
    {
        return array_values(Category::query()
            ->active()
            ->whereRaw($this->likeExpression('name'), [$this->containsPattern($term)])
            ->orderBy('name')
            ->take(self::TAXONOMY_LIMIT)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category): FacetOptionData => new FacetOptionData(
                id: $category->getKey(),
                name: $category->name,
                slug: $category->slug,
                count: 0,
            ))
            ->all());
    }

    /**
     * @return list<FacetOptionData>
     */
    private function brands(string $term): array
    {
        return array_values(Brand::query()
            ->active()
            ->whereRaw($this->likeExpression('name'), [$this->containsPattern($term)])
            ->whereHas('products', $this->constrainToSearchableProducts(...))
            ->orderBy('name')
            ->take(self::TAXONOMY_LIMIT)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Brand $brand): FacetOptionData => new FacetOptionData(
                id: $brand->getKey(),
                name: $brand->name,
                slug: $brand->slug,
                count: 0,
            ))
            ->all());
    }
}

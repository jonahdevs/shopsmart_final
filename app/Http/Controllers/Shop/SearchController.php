<?php

namespace App\Http\Controllers\Shop;

use App\Data\FacetOptionData;
use App\Data\ProductCardData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\SearchSuggestRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    use FiltersCatalogProducts;

    /** Products offered in the header dropdown. */
    private const PRODUCT_LIMIT = 6;

    /** Category and brand shortcuts offered alongside them. */
    private const TAXONOMY_LIMIT = 4;

    /**
     * Answer the header's autocomplete.
     *
     * Products come through Scout so the ranking matches the search page, while
     * categories and brands are plain lookups — they are a handful of rows and
     * are not worth indexing. The two-character minimum lives in the form
     * request, so a single keystroke never reaches the search engine at all.
     */
    public function __invoke(SearchSuggestRequest $request): JsonResponse
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
        return array_values(Product::search($term)
            ->query($this->constrainToLiveSearchResults(...))
            ->take(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * Scout hands its `query` callback a plain builder, so the storefront's
     * visibility rules are applied here rather than inline — the search index
     * knows nothing about publication status or stock.
     *
     * @param  Builder<Product>  $query
     */
    private function constrainToLiveSearchResults(Builder $query): void
    {
        $this->constrainToSearchableProducts($query);

        $query
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->honorStockVisibility();
    }

    /**
     * Live, search-visible products — the definition a brand shortcut has to
     * agree with, or the header would offer a brand whose results page is empty.
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

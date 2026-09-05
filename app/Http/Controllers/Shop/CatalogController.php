<?php

namespace App\Http\Controllers\Shop;

use App\Data\ProductListData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Category;
use App\Support\CategoryTree;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    use FiltersCatalogProducts;

    /**
     * Show the faceted catalog listing.
     *
     * `products` is a merge prop targeting its own `data` array, so a partial
     * reload for the next page appends tiles instead of replacing the grid,
     * while the counters around it are replaced wholesale. Changing a filter is
     * the same request with `only: ['products']` and the page reset to 1.
     */
    public function index(CatalogFilterRequest $request): Response
    {
        $filters = $this->filtersFrom($request->validated());

        // One tree read serves both the category scope and the rolled-up facet
        // counts, the same way the category page uses it.
        $tree = CategoryTree::load();

        $query = $this->catalogQuery();

        $selectedIds = $filters->categories === []
            ? null
            : $this->selectedCategoryIds($filters->categories, $tree);

        if ($selectedIds !== null) {
            $this->scopeToCategories($query, $selectedIds);
        }

        $this->applyFilters($query, $filters);

        return Inertia::render('shop/Catalog', [
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->categoryFacets($this->catalogProductIdsByCategory(), $tree),
            // The same ids the listing is pinned to, so a brand's number is
            // what ticking it returns rather than its store-wide total.
            'brandFacets' => $this->brandFacets($selectedIds),
        ]);
    }

    /**
     * Resolve the ticked category slugs to ids, each expanded through its whole
     * subtree. A top-level category holds no products of its own, so resolving
     * the slug alone returned an empty grid for a box the sidebar offered.
     *
     * An unknown slug resolves to nothing, which correctly empties the grid
     * rather than being ignored.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function selectedCategoryIds(array $slugs, CategoryTree $tree): array
    {
        $expanded = [];

        foreach (Category::query()->whereIn('slug', $slugs)->pluck('id') as $id) {
            foreach ($tree->subtreeIds((int) $id) as $descendantId) {
                $expanded[$descendantId] = true;
            }
        }

        return array_keys($expanded);
    }
}

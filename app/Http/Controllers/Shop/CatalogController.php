<?php

namespace App\Http\Controllers\Shop;

use App\Data\ProductListData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Category;
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

        $query = $this->catalogQuery();

        if ($filters->categories !== []) {
            $this->scopeToCategories($query, $this->selectedCategoryIds($filters->categories));
        }

        $this->applyFilters($query, $filters);

        return Inertia::render('shop/Catalog', [
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->categoryFacets($this->catalogCountsByCategory()),
            'brandFacets' => $this->brandFacets(),
        ]);
    }

    /**
     * Resolve the ticked category slugs to ids. An unknown slug resolves to
     * nothing, which correctly empties the grid rather than being ignored.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function selectedCategoryIds(array $slugs): array
    {
        /** @var list<int> $ids */
        $ids = Category::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }
}

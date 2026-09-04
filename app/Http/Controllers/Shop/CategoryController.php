<?php

namespace App\Http\Controllers\Shop;

use App\Data\CategoryData;
use App\Data\FacetOptionData;
use App\Data\ProductListData;
use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\BuildsCategoryBreadcrumbs;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use BuildsCategoryBreadcrumbs, FiltersCatalogProducts;

    /**
     * Show every active category with its live product count.
     */
    public function index(): Response
    {
        $counts = $this->catalogCountsByCategory();

        $categories = Category::query()
            ->active()
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $categories->groupBy('parent_id');

        return Inertia::render('shop/Categories', [
            'categories' => array_values($categories
                ->map(fn (Category $category): CategoryData => CategoryData::fromModel(
                    $category,
                    array_values($byParent->get($category->getKey(), new Collection)
                        ->map(fn (Category $child): CategoryData => CategoryData::fromModel(
                            $child,
                            productCount: $counts[$child->getKey()] ?? 0,
                        ))
                        ->all()),
                    $counts[$category->getKey()] ?? 0,
                ))
                ->all()),
        ]);
    }

    /**
     * Show one category's faceted listing, rolled up over its whole subtree.
     *
     * Same engine as the catalog, pinned to the subtree. `cat[]` here narrows
     * to child categories, and every selected id is intersected back against
     * the subtree so a hand-edited URL cannot widen the listing beyond the
     * category the shopper is looking at.
     */
    public function show(CatalogFilterRequest $request, Category $category): Response
    {
        abort_unless($category->status === CategoryStatus::Active, 404);

        $filters = $this->filtersFrom($request->validated());

        // One tree read answers all four jobs below: the subtree the listing is
        // pinned to, the per-child subtree counts, the facet counts and the
        // breadcrumb trail.
        $tree = CategoryTree::load();
        $subtreeIds = $tree->subtreeIds($category->getKey());

        $query = $this->catalogQuery();
        $this->scopeToCategories($query, $this->scopedCategoryIds($filters->categories, $subtreeIds, $tree));
        $this->applyFilters($query, $filters);

        $counts = $this->catalogCountsByCategory();
        $children = $this->activeChildren($category);

        return Inertia::render('shop/Category', [
            'category' => CategoryData::fromModel(
                $category,
                array_values($children
                    ->map(fn (Category $child): CategoryData => CategoryData::fromModel(
                        $child,
                        productCount: $tree->subtreeCount($child->getKey(), $counts),
                    ))
                    ->all()),
                $tree->subtreeCount($category->getKey(), $counts),
            ),
            'breadcrumbs' => $this->categoryBreadcrumbs($category, $tree),
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->childFacets($children, $tree, $counts),
            'brandFacets' => $this->brandFacets($subtreeIds),
        ]);
    }

    /**
     * The ids the listing is pinned to: the selected child subtrees when the
     * shopper has narrowed by category, otherwise the whole subtree. Selected
     * ids are intersected with the subtree, so `?cat[]=` pointing at an
     * unrelated category filters to nothing rather than escaping the scope.
     *
     * @param  list<string>  $selectedSlugs
     * @param  list<int>  $subtreeIds
     * @return list<int>
     */
    private function scopedCategoryIds(array $selectedSlugs, array $subtreeIds, CategoryTree $tree): array
    {
        if ($selectedSlugs === []) {
            return $subtreeIds;
        }

        $selected = Category::query()->whereIn('slug', $selectedSlugs)->pluck('id');

        $expanded = [];

        foreach ($selected as $id) {
            foreach ($tree->subtreeIds((int) $id) as $descendantId) {
                $expanded[$descendantId] = true;
            }
        }

        return array_values(array_intersect(array_keys($expanded), $subtreeIds));
    }

    /**
     * @return Collection<int, Category>
     */
    private function activeChildren(Category $category): Collection
    {
        return Category::query()
            ->active()
            ->where('parent_id', $category->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);
    }

    /**
     * Children that actually hold products, counted across their own subtrees.
     * A child with an empty subtree is dropped: ticking it could only ever
     * empty the grid.
     *
     * @param  Collection<int, Category>  $children
     * @param  array<int, int>  $counts
     * @return list<FacetOptionData>
     */
    private function childFacets(Collection $children, CategoryTree $tree, array $counts): array
    {
        $facets = [];

        foreach ($children as $child) {
            $count = $tree->subtreeCount($child->getKey(), $counts);

            if ($count === 0) {
                continue;
            }

            $facets[] = new FacetOptionData(
                id: $child->getKey(),
                name: $child->name,
                slug: $child->slug,
                count: $count,
            );
        }

        return $facets;
    }
}

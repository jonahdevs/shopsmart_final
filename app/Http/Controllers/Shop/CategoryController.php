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
     * Show the active taxonomy as roots, each carrying its children.
     *
     * Only roots are returned. Sending the flat list *and* nesting each
     * category's children under it put every child in the payload twice and
     * left the page to work out the difference.
     *
     * Counts are rolled up over the subtree, the same way {@see self::show()}
     * counts them — a category that reports "12" here has to list 12 when the
     * shopper clicks it.
     */
    public function index(): Response
    {
        $productIdsByCategory = $this->catalogProductIdsByCategory();
        $tree = CategoryTree::load();

        $categories = Category::query()
            ->active()
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $categories->groupBy('parent_id');
        $active = $categories->keyBy(fn (Category $category): int => $category->getKey());

        // A root is a category nothing active claims as a child: either it sits
        // at the top, or its parent is not itself active. Without the second
        // case an active child of a drafted parent would vanish from the index
        // while still being reachable at its own URL.
        $roots = $categories->filter(
            fn (Category $category): bool => $category->parent_id === null
                || ! $active->has($category->parent_id),
        );

        return Inertia::render('shop/Categories', [
            'categories' => array_values($roots
                ->map(fn (Category $category): CategoryData => CategoryData::fromModel(
                    $category,
                    array_values($byParent->get($category->getKey(), new Collection)
                        ->map(fn (Category $child): CategoryData => CategoryData::fromModel(
                            $child,
                            productCount: $tree->subtreeCount($child->getKey(), $productIdsByCategory),
                        ))
                        ->all()),
                    $tree->subtreeCount($category->getKey(), $productIdsByCategory),
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

        // The route binding resolves the category without its media, and
        // CategoryData reads a cover off it. Media library is configured to
        // force lazy loading, so the missed eager load is a silent extra query
        // rather than a lazy-loading violation.
        $category->loadMissing('media');

        $filters = $this->filtersFrom($request->validated());

        // One tree read answers all four jobs below: the subtree the listing is
        // pinned to, the per-child subtree counts, the facet counts and the
        // breadcrumb trail.
        $tree = CategoryTree::load();
        $subtreeIds = $tree->subtreeIds($category->getKey());

        $query = $this->catalogQuery();
        $this->scopeToCategories($query, $this->scopedCategoryIds($filters->categories, $subtreeIds, $tree));
        $this->applyFilters($query, $filters);

        $productIdsByCategory = $this->catalogProductIdsByCategory();
        $children = $this->activeChildren($category);

        return Inertia::render('shop/Category', [
            'category' => CategoryData::fromModel(
                $category,
                array_values($children
                    ->map(fn (Category $child): CategoryData => CategoryData::fromModel(
                        $child,
                        productCount: $tree->subtreeCount($child->getKey(), $productIdsByCategory),
                    ))
                    ->all()),
                $tree->subtreeCount($category->getKey(), $productIdsByCategory),
            ),
            'breadcrumbs' => $this->categoryBreadcrumbs($category, $tree),
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->childFacets($children, $tree, $productIdsByCategory),
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
     * The child tiles, with their cover art.
     *
     * Every column is selected: CategoryData renders `description` and
     * `icon_svg`, and a narrowed column list handed both to the tile as null.
     * Media is eager loaded because the tile resolves a cover per child, which
     * media library's forced lazy loading would turn into a query each.
     *
     * @return Collection<int, Category>
     */
    private function activeChildren(Category $category): Collection
    {
        return Category::query()
            ->active()
            ->with('media')
            ->where('parent_id', $category->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Children that actually hold products, counted across their own subtrees.
     * A child with an empty subtree is dropped: ticking it could only ever
     * empty the grid.
     *
     * @param  Collection<int, Category>  $children
     * @param  array<int, list<int>>  $productIdsByCategory
     * @return list<FacetOptionData>
     */
    private function childFacets(Collection $children, CategoryTree $tree, array $productIdsByCategory): array
    {
        $facets = [];

        foreach ($children as $child) {
            $count = $tree->subtreeCount($child->getKey(), $productIdsByCategory);

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

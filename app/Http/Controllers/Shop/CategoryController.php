<?php

namespace App\Http\Controllers\Shop;

use App\Data\BreadcrumbData;
use App\Data\CategoryData;
use App\Data\FacetOptionData;
use App\Data\ProductListData;
use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use FiltersCatalogProducts;

    /** How deep a breadcrumb trail may walk before we assume the tree is cyclic. */
    private const MAX_TREE_DEPTH = 10;

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

        $subtreeIds = $category->descendantIds();
        $parentByChild = $this->categoryEdges();
        $childrenByParent = $this->childrenByParent($parentByChild);

        $query = $this->catalogQuery();
        $this->scopeToCategories($query, $this->scopedCategoryIds($filters->categories, $subtreeIds, $childrenByParent));
        $this->applyFilters($query, $filters);

        $counts = $this->catalogCountsByCategory();
        $children = $this->activeChildren($category);

        return Inertia::render('shop/Category', [
            'category' => CategoryData::fromModel(
                $category,
                array_values($children
                    ->map(fn (Category $child): CategoryData => CategoryData::fromModel(
                        $child,
                        productCount: $this->subtreeCount($child->getKey(), $childrenByParent, $counts),
                    ))
                    ->all()),
                $this->subtreeCount($category->getKey(), $childrenByParent, $counts),
            ),
            'breadcrumbs' => $this->breadcrumbs($category, $parentByChild),
            'products' => Inertia::merge(
                fn (): ProductListData => ProductListData::fromPaginator($this->paginateCatalog($query)),
            )->append('data', 'id'),
            'filters' => $filters,
            'categoryFacets' => $this->childFacets($children, $childrenByParent, $counts),
            'brandFacets' => $this->brandFacets($subtreeIds),
        ]);
    }

    /**
     * Every category's parent, in one query. Cheaper than walking the tree with
     * a query per level, and the basis for both the breadcrumb trail and the
     * per-child subtree counts.
     *
     * @return array<int, int> category id => parent id
     */
    private function categoryEdges(): array
    {
        /** @var array<int, int> $edges */
        $edges = Category::query()
            ->whereNotNull('parent_id')
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $parentId): int => (int) $parentId)
            ->all();

        return $edges;
    }

    /**
     * Invert the edge list once, so a subtree walk is a lookup rather than a
     * rebuild per category.
     *
     * @param  array<int, int>  $parentByChild
     * @return array<int, list<int>>
     */
    private function childrenByParent(array $parentByChild): array
    {
        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach ($parentByChild as $id => $parentId) {
            $childrenByParent[$parentId][] = $id;
        }

        return $childrenByParent;
    }

    /**
     * The ids the listing is pinned to: the selected child subtrees when the
     * shopper has narrowed by category, otherwise the whole subtree. Selected
     * ids are intersected with the subtree, so `?cat[]=` pointing at an
     * unrelated category filters to nothing rather than escaping the scope.
     *
     * @param  list<string>  $selectedSlugs
     * @param  list<int>  $subtreeIds
     * @param  array<int, list<int>>  $childrenByParent
     * @return list<int>
     */
    private function scopedCategoryIds(array $selectedSlugs, array $subtreeIds, array $childrenByParent): array
    {
        if ($selectedSlugs === []) {
            return $subtreeIds;
        }

        $selected = Category::query()->whereIn('slug', $selectedSlugs)->pluck('id');

        $expanded = [];

        foreach ($selected as $id) {
            foreach ($this->subtreeIds((int) $id, $childrenByParent) as $descendantId) {
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
     * @param  Collection<int, Category>  $children
     * @param  array<int, list<int>>  $childrenByParent
     * @param  array<int, int>  $counts
     * @return list<FacetOptionData>
     */
    private function childFacets(Collection $children, array $childrenByParent, array $counts): array
    {
        $facets = [];

        foreach ($children as $child) {
            $count = $this->subtreeCount($child->getKey(), $childrenByParent, $counts);

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

    /**
     * Live catalog products across a category and everything beneath it.
     *
     * @param  array<int, list<int>>  $childrenByParent
     * @param  array<int, int>  $counts
     */
    private function subtreeCount(int $categoryId, array $childrenByParent, array $counts): int
    {
        $total = 0;

        foreach ($this->subtreeIds($categoryId, $childrenByParent) as $id) {
            $total += $counts[$id] ?? 0;
        }

        return $total;
    }

    /**
     * A category id plus every descendant, walked in memory from the edge list.
     *
     * @param  array<int, list<int>>  $childrenByParent
     * @return list<int>
     */
    private function subtreeIds(int $categoryId, array $childrenByParent): array
    {
        $ids = [$categoryId];
        $seen = [$categoryId => true];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * Home / Categories / ancestors / this category. Ancestor ids come out of
     * the edge list, so the whole trail costs one extra query however deep the
     * category sits.
     *
     * @param  array<int, int>  $parentByChild
     * @return list<BreadcrumbData>
     */
    private function breadcrumbs(Category $category, array $parentByChild): array
    {
        $ancestorIds = [];
        $current = $parentByChild[$category->getKey()] ?? null;
        $depth = 0;

        while ($current !== null && $depth < self::MAX_TREE_DEPTH) {
            $ancestorIds[] = $current;
            $current = $parentByChild[$current] ?? null;
            $depth++;
        }

        $trail = [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: __('Categories'), slug: null),
        ];

        if ($ancestorIds !== []) {
            $ancestors = Category::query()
                ->whereIn('id', $ancestorIds)
                ->get(['id', 'name', 'slug'])
                ->keyBy('id');

            foreach (array_reverse($ancestorIds) as $id) {
                $ancestor = $ancestors->get($id);

                if ($ancestor !== null) {
                    $trail[] = new BreadcrumbData(name: $ancestor->name, slug: $ancestor->slug);
                }
            }
        }

        $trail[] = new BreadcrumbData(name: $category->name, slug: $category->slug);

        return $trail;
    }
}

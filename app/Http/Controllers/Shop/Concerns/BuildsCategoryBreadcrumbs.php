<?php

namespace App\Http\Controllers\Shop\Concerns;

use App\Data\BreadcrumbData;
use App\Models\Category;
use App\Support\CategoryTree;

/**
 * The storefront breadcrumb trail.
 *
 * Every trail starts Home / Categories and then walks down the catalog tree, so
 * the category page and the product page were building the same thing from
 * their own copies of the walk. The product page appends itself to what this
 * returns; the category page is what this returns.
 */
trait BuildsCategoryBreadcrumbs
{
    /**
     * Home / Categories / every ancestor / this category.
     *
     * Ancestor ids come from the already-loaded tree, so the trail costs one
     * query for their names however deep the category sits. A null category —
     * a product filed directly under nothing — stops after Categories.
     *
     * @return list<BreadcrumbData>
     */
    protected function categoryBreadcrumbs(?Category $category, CategoryTree $tree): array
    {
        $trail = [
            new BreadcrumbData(name: __('Home'), slug: null),
            new BreadcrumbData(name: __('Categories'), slug: null),
        ];

        if ($category === null) {
            return $trail;
        }

        $ancestorIds = $tree->ancestorIds($category->getKey());

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

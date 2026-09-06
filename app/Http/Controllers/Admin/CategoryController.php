<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminCategoryFormData;
use App\Data\AdminCategoryOptionData;
use App\Data\AdminCategoryRowData;
use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryIndexRequest;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalog tree.
 *
 * Categories, brands and attributes are catalog *structure* rather than
 * merchandise, and move together under one `catalog.manage` permission: a role
 * trusted to restructure the taxonomy is trusted with all of it.
 *
 * The table is a tree walked depth-first from the roots rather than a paginated
 * list. Paging a tree would cut it across a parent, and there are dozens of
 * categories rather than thousands — the shape is the information here.
 *
 * Two rules keep the tree walkable, and both are refusals rather than
 * corrections. A category may not sit under itself or one of its descendants
 * ({@see CategoryRequest}), and a category with
 * children may not be deleted: `categories.parent_id` is `nullOnDelete`, so
 * deleting a parent would silently promote its children to roots — a
 * reorganisation nobody asked for, presented as a delete.
 */
class CategoryController extends Controller
{
    use BuildsLikeQueries;

    public function index(CategoryIndexRequest $request): Response
    {
        $categories = Category::query()
            ->withCount('children')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productCounts = $this->productCountsByCategory();

        return Inertia::render('admin/categories/Index', [
            'categories' => $this->rows($categories, $productCounts),
            'filters' => [
                'search' => $request->validated('search'),
                'status' => $request->validated('status'),
            ],
            'statusOptions' => CategoryStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => AdminCategoryFormData::blank(),
            'parentOptions' => $this->parentOptions(null),
            'statusOptions' => CategoryStatus::options(),
        ]);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => AdminCategoryFormData::fromModel($category),
            'parentOptions' => $this->parentOptions($category),
            'statusOptions' => CategoryStatus::options(),
        ]);
    }

    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        Category::query()->create($request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.categories.index');
    }

    public function update(CategoryUpdateRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Delete a leaf.
     *
     * Products filed here are not deleted with it — `primary_category_id` is
     * nulled and the pivot rows cascade — so the merchandise survives an
     * uncategorised. Children do not get that treatment silently: a parent with
     * children is refused, and the staff member moves or removes them first.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors([
                'category' => __('Move or remove this category\'s subcategories before deleting it.'),
            ]);
        }

        DB::transaction(function () use ($category): void {
            $category->products()->detach();
            $category->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.categories.index');
    }

    /**
     * @param  Builder<Category>  $query
     */
    private function applyFilters(Builder $query, CategoryIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('slug'), [$pattern]);
            });
        }

        $query->when(
            $request->validated('status'),
            fn (Builder $q, string $status) => $q->where('status', $status),
        );
    }

    /**
     * The tree flattened into table rows, parents before their children.
     *
     * A category whose parent was filtered out becomes a root here rather than
     * disappearing — a search for "grinders" must show the matches, not hide
     * the ones whose parent did not match.
     *
     * @param  Collection<int, Category>  $categories
     * @param  array<int, int>  $productCounts
     * @return list<AdminCategoryRowData>
     */
    private function rows(Collection $categories, array $productCounts): array
    {
        $present = [];
        $childrenByParent = [];

        foreach ($categories as $category) {
            $present[(int) $category->getKey()] = true;
        }

        foreach ($categories as $category) {
            $parentId = $category->parent_id;
            $key = $parentId !== null && isset($present[$parentId]) ? $parentId : 0;
            $childrenByParent[$key][] = $category;
        }

        $rows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $childrenByParent, $productCounts): void {
            foreach ($childrenByParent[$parentId] ?? [] as $category) {
                $id = (int) $category->getKey();

                $rows[] = AdminCategoryRowData::fromModel(
                    $category,
                    $depth,
                    $productCounts[$id] ?? 0,
                    (int) ($category->getAttribute('children_count') ?? 0),
                );

                $walk($id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    /**
     * How many distinct products each category holds directly.
     *
     * Membership is `primary_category_id` OR the `category_product` pivot and a
     * product is usually in both, so the two sources are rolled up into a set
     * of ids per category rather than added — adding the two counts would
     * report roughly twice the products the category actually holds.
     *
     * Two queries for the whole table, not one per row.
     *
     * @return array<int, int>
     */
    private function productCountsByCategory(): array
    {
        $idsByCategory = [];

        $pivot = DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->whereNull('products.deleted_at')
            ->get(['category_product.category_id', 'category_product.product_id']);

        foreach ($pivot as $row) {
            $idsByCategory[(int) $row->category_id][(int) $row->product_id] = true;
        }

        $primary = DB::table('products')
            ->whereNotNull('primary_category_id')
            ->whereNull('deleted_at')
            ->get(['primary_category_id', 'id']);

        foreach ($primary as $row) {
            $idsByCategory[(int) $row->primary_category_id][(int) $row->id] = true;
        }

        return array_map(count(...), $idsByCategory);
    }

    /**
     * The categories that may be chosen as a parent.
     *
     * The category's own subtree is left out, so the cycle the request refuses
     * cannot be offered in the first place. The refusal still stands on its own
     * — a hidden option is not a rule.
     *
     * @return list<AdminCategoryOptionData>
     */
    private function parentOptions(?Category $category): array
    {
        $all = Category::query()->orderBy('sort_order')->orderBy('name')->get();

        if ($category === null) {
            return AdminCategoryOptionData::tree($all);
        }

        $ownSubtree = CategoryTree::load()->subtreeIds($category->getKey());

        return AdminCategoryOptionData::tree(
            $all->reject(fn (Category $candidate): bool => in_array((int) $candidate->getKey(), $ownSubtree, true)),
        );
    }
}

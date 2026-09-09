<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminProductRowData;
use App\Data\AdminTagProductOptionData;
use App\Data\AdminTagRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagProductIndexRequest;
use App\Http\Requests\Admin\TagProductRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Tags\Tag;

/**
 * The products carrying one tag, and the two clicks that change that list.
 *
 * Separate from {@see TagController} because it answers a different question:
 * that one is about the tag as a record, this one is about its membership. It
 * is also the only screen in the catalog where the *contents* of a storefront
 * rail can be seen as a list — the product editor can put a tag on a product,
 * but nothing there tells a merchandiser what else is already on the shelf.
 *
 * Adding and removing go through the model rather than the pivot table, so
 * `attachTag` / `detachTag` stay the one definition of what membership means.
 * Both are idempotent, which is why neither is refused: re-adding a product
 * that is already tagged is a harmless request about a real row, and saying so
 * plainly is better than a validation error.
 */
class TagProductController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the tagged-products table. */
    private const PER_PAGE = 25;

    /** How many candidates the add box offers at once. */
    private const CANDIDATE_LIMIT = 10;

    public function index(TagProductIndexRequest $request, Tag $tag): Response
    {
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') === 'desc' ? 'desc' : 'asc';

        $products = Product::query()
            // Eager loaded for the row: without `media`, getFirstMedia() is a
            // query per product.
            ->with(['brand:id,name', 'primaryCategory:id,name', 'media'])
            ->withCount('variants')
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->getKey()))
            ->tap(fn (Builder $query) => $this->applySearch($query, $request->validated('search')))
            ->orderBy(is_string($sort) ? $sort : 'name', $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/tags/Products', [
            'tag' => AdminTagRowData::fromModel($tag),
            'products' => array_values(array_map(
                fn (Product $product): AdminProductRowData => AdminProductRowData::fromModel($product),
                $products->items(),
            )),
            'pagination' => PaginationData::fromPaginator($products),
            'candidates' => $this->candidates($tag, $request->validated('add')),
            'filters' => [
                'search' => $request->validated('search'),
                'add' => $request->validated('add'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(TagProductRequest $request, Tag $tag): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->productId());
        $product->attachTag($tag);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':product now carries this tag.', ['product' => $product->name]),
        ]);

        // Back rather than to the index, so the search the staff member was
        // adding from survives the click and they can add the next one.
        return back();
    }

    public function destroy(Tag $tag, Product $product): RedirectResponse
    {
        $product->detachTag($tag);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':product no longer carries this tag.', ['product' => $product->name]),
        ]);

        return back();
    }

    /**
     * Products that could be added, for the picker.
     *
     * Empty until something is typed, deliberately: an unfiltered list of the
     * whole catalog is not a picker, and rendering the first ten products
     * alphabetically would invite a click on whichever one happened to be
     * there. Ones already carrying the tag are excluded rather than shown as
     * disabled — the list is short and its whole job is "what can I add".
     *
     * @return list<AdminTagProductOptionData>
     */
    private function candidates(Tag $tag, mixed $term): array
    {
        if (! is_string($term) || trim($term) === '') {
            return [];
        }

        $products = Product::query()
            ->select(['id', 'name', 'sku'])
            ->whereDoesntHave('tags', fn (Builder $query) => $query->whereKey($tag->getKey()))
            ->tap(fn (Builder $query) => $this->applySearch($query, $term))
            ->orderBy('name')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return array_values($products
            ->map(fn (Product $product): AdminTagProductOptionData => AdminTagProductOptionData::fromModel($product))
            ->all());
    }

    /**
     * Name or SKU, escaped so a typed wildcard matches itself.
     *
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, mixed $term): void
    {
        if (! is_string($term) || trim($term) === '') {
            return;
        }

        $pattern = $this->containsPattern(trim($term));

        $query->where(function (Builder $match) use ($pattern): void {
            $match
                ->whereRaw($this->likeExpression('name'), [$pattern])
                ->orWhereRaw($this->likeExpression('sku'), [$pattern]);
        });
    }
}

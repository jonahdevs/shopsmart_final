<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminBrandFormData;
use App\Data\AdminBrandRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandIndexRequest;
use App\Http\Requests\Admin\BrandStoreRequest;
use App\Http\Requests\Admin\BrandUpdateRequest;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manufacturers and marques.
 *
 * Structure rather than merchandise, so `catalog.manage` covers the whole
 * resource — there is no read-only half the way products have one.
 *
 * Deleting a brand does not delete its products: `products.brand_id` is
 * `nullOnDelete`, so they become unbranded and stay sellable. That is a real
 * consequence rather than a silent one, so the table prints the product count
 * next to every brand and the delete confirmation repeats it.
 */
class BrandController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the brands table. */
    private const PER_PAGE = 25;

    public function index(BrandIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') ?? 'asc';

        $brands = Brand::query()
            // An aggregate, not a loaded relation: this is the number staff
            // check before deleting a brand, and a popular one has hundreds of
            // products behind it.
            ->withCount('products')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy(is_string($sort) ? $sort : 'name', $direction === 'desc' ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/brands/Index', [
            'brands' => array_values(array_map(
                fn (Brand $brand): AdminBrandRowData => AdminBrandRowData::fromModel($brand),
                $brands->items(),
            )),
            'pagination' => PaginationData::fromPaginator($brands),
            'filters' => [
                'search' => $request->validated('search'),
                'active' => $request->validated('active'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/brands/Form', [
            'brand' => AdminBrandFormData::blank(),
        ]);
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('admin/brands/Form', [
            'brand' => AdminBrandFormData::fromModel($brand),
        ]);
    }

    public function store(BrandStoreRequest $request): RedirectResponse
    {
        Brand::query()->create($request->brandAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand created.')]);

        return to_route('admin.brands.index');
    }

    public function update(BrandUpdateRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->brandAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand saved.')]);

        return to_route('admin.brands.index');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Brand deleted. Its products are now unbranded.'),
        ]);

        return to_route('admin.brands.index');
    }

    /**
     * @param  Builder<Brand>  $query
     */
    private function applyFilters(Builder $query, BrandIndexRequest $request): void
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

        $active = $request->validated('active');

        if ($active !== null) {
            $query->where('is_active', $active === '1');
        }
    }
}

<?php

namespace App\Data;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A page of catalog products plus the cursor a "load more" button needs.
 *
 * The shape is deliberately flat rather than Laravel's paginator envelope: the
 * storefront appends pages client-side through an Inertia merge prop targeting
 * `data`, and everything outside `data` is replaced wholesale on each reload.
 */
#[TypeScript]
class ProductListData extends Data
{
    /**
     * @param  list<ProductCardData>  $data
     */
    public function __construct(
        public array $data,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public bool $hasMorePages,
    ) {}

    /**
     * A page of nothing, for a listing that deliberately never ran a query —
     * the search page before the shopper has typed enough to search on. Built
     * rather than paginated so an unanswerable request costs no queries at all.
     *
     * Named `emptyPage` rather than `empty`, which laravel-data already
     * declares on the base class with an incompatible signature.
     */
    public static function emptyPage(int $perPage): self
    {
        return new self(
            data: [],
            currentPage: 1,
            lastPage: 1,
            perPage: $perPage,
            total: 0,
            hasMorePages: false,
        );
    }

    /**
     * @param  LengthAwarePaginator<int, Product>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            data: array_values(array_map(
                fn (Product $product): ProductCardData => ProductCardData::fromModel($product),
                $paginator->items(),
            )),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            hasMorePages: $paginator->hasMorePages(),
        );
    }
}

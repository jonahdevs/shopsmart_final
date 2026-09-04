<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The listing's active filter state, echoed back so the sidebar can render
 * itself from the server's reading of the query string rather than re-parsing
 * the URL. Prices are whole KES here — the cents live in the database.
 */
#[TypeScript]
class CatalogFilterData extends Data
{
    /**
     * @param  list<string>  $categories  Category slugs.
     * @param  list<int>  $brands  Brand ids.
     */
    public function __construct(
        public string $q,
        public array $categories,
        public array $brands,
        public int $priceMin,
        public int $priceMax,
        public bool $inStockOnly,
        public int $minRating,
        public string $tag,
        public bool $newArrivalsOnly,
        public string $sort,
        public bool $hasActiveFilters,
    ) {}
}

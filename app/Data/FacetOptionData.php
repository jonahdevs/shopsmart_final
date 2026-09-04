<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One checkbox in a listing's filter sidebar, with the number of products it
 * would leave behind. Only facets with at least one product are published, so
 * a shopper can never tick a box that empties the grid.
 *
 * Categories are filtered by slug and brands by id, so both keys are carried.
 */
#[TypeScript]
class FacetOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $count,
    ) {}
}

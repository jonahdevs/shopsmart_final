<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the compare table.
 *
 * `values` is positional: index n is the value for the product at index n of
 * {@see CompareData::$products}, and null where that product does not declare
 * the attribute at all. Aligning server-side keeps the Vue table a plain
 * two-level v-for with no lookup logic.
 */
#[TypeScript]
class CompareAttributeData extends Data
{
    /**
     * @param  list<string|null>  $values
     */
    public function __construct(
        public string $name,
        public array $values,
    ) {}
}

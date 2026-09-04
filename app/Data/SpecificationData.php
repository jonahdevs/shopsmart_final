<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the product page's specification table. A spec may carry several
 * values ("Material: Steel, Aluminium"), so values is always a list.
 */
#[TypeScript]
class SpecificationData extends Data
{
    /**
     * @param  list<string>  $values
     */
    public function __construct(
        public string $name,
        public array $values,
    ) {}
}

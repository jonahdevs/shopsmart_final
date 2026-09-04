<?php

namespace App\Data;

use App\Enums\AttributeType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One axis a variable product varies along (Size, Colour) together with the
 * values this particular product offers on it. `type` says how the values
 * should render: dropdown, colour swatch or button group.
 */
#[TypeScript]
class VariationAttributeData extends Data
{
    /**
     * @param  list<AttributeValueData>  $values
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public AttributeType $type,
        public array $values,
    ) {}
}

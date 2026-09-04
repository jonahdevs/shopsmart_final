<?php

namespace App\Data;

use App\Models\AttributeValue;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One selectable option of a variation attribute — "Large", "Midnight Blue".
 */
#[TypeScript]
class AttributeValueData extends Data
{
    public function __construct(
        public int $id,
        public int $attributeId,
        public string $value,
        public string $label,
        public string $slug,
        /** Hex swatch, only set when the parent attribute is a colour. */
        public ?string $colorCode,
    ) {}

    public static function fromModel(AttributeValue $value): self
    {
        return new self(
            id: $value->getKey(),
            attributeId: $value->attribute_id,
            value: $value->value,
            label: $value->label,
            slug: $value->slug,
            colorCode: $value->color_code,
        );
    }
}

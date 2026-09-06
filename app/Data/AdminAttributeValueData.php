<?php

namespace App\Data;

use App\Models\AttributeValue;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the attribute editor's values repeater.
 *
 * Deliberately not {@see AttributeValueData}: that object is the storefront's
 * swatch — label, colour and nothing a shopper cannot see. This one carries the
 * `value` and `slug` a staff member sets, and `variantCount`, which is what
 * says whether removing the row would unpick a purchasable variant.
 */
#[TypeScript]
class AdminAttributeValueData extends Data
{
    public function __construct(
        public int $id,
        public string $value,
        public string $label,
        public string $slug,
        public ?string $colorCode,
        public int $sortOrder,
        public bool $isActive,
        public int $variantCount,
    ) {}

    public static function fromModel(AttributeValue $value): self
    {
        return new self(
            id: $value->getKey(),
            value: $value->value,
            label: $value->label,
            slug: $value->slug,
            colorCode: $value->color_code,
            sortOrder: $value->sort_order,
            isActive: $value->is_active,
            variantCount: (int) ($value->getAttribute('variants_count') ?? 0),
        );
    }
}

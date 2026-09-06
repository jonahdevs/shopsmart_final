<?php

namespace App\Data;

use App\Models\Attribute;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin attributes table.
 *
 * `productCount` counts the products that declare this attribute, and is what
 * stands between a staff member and destroying a variant axis: the FK from
 * `product_attributes` cascades, so deleting an attribute in use would silently
 * unpick every variant defined by it. The controller refuses that; this is how
 * the table says so before they try.
 */
#[TypeScript]
class AdminAttributeRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $type,
        public string $typeLabel,
        public bool $isActive,
        public int $sortOrder,
        public int $valueCount,
        public int $productCount,
    ) {}

    public static function fromModel(Attribute $attribute): self
    {
        return new self(
            id: $attribute->getKey(),
            name: $attribute->name,
            slug: $attribute->slug,
            type: $attribute->type->value,
            typeLabel: $attribute->type->label(),
            isActive: $attribute->is_active,
            sortOrder: $attribute->sort_order,
            valueCount: (int) ($attribute->getAttribute('values_count') ?? 0),
            productCount: (int) ($attribute->getAttribute('product_attributes_count') ?? 0),
        );
    }
}

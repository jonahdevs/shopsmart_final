<?php

namespace App\Data;

use App\Models\Attribute;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An attribute and its values, as one editor holds them.
 *
 * Values are edited inline rather than through routes of their own: a value has
 * no meaning apart from its attribute, and splitting them would let a staff
 * member save "Colour" and its swatches in two steps that can each fail
 * separately. One form, one transaction.
 */
#[TypeScript]
class AdminAttributeFormData extends Data
{
    /**
     * @param  list<AdminAttributeValueData>  $values
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $slug,
        public string $type,
        public bool $isActive,
        public int $sortOrder,
        public array $values,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            name: '',
            slug: null,
            type: 'select',
            isActive: true,
            sortOrder: 0,
            values: [],
        );
    }

    /** Expects `values` loaded, with a `variants_count` on each. */
    public static function fromModel(Attribute $attribute): self
    {
        return new self(
            id: $attribute->getKey(),
            name: $attribute->name,
            slug: $attribute->slug,
            type: $attribute->type->value,
            isActive: $attribute->is_active,
            sortOrder: $attribute->sort_order,
            values: array_values($attribute->values
                ->map(fn ($value): AdminAttributeValueData => AdminAttributeValueData::fromModel($value))
                ->all()),
        );
    }
}

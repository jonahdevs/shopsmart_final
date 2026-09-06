<?php

namespace App\Data;

use App\Models\ProductLink;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the product editor's "related products" repeater.
 *
 * `isRequired` and `defaultQuantity` are accessory-only in meaning — they drive
 * the storefront's "complete your purchase" prompt — but they live on every
 * row because the table does, and hiding them per type in the editor would
 * make the form's shape depend on a select the staff member can change.
 */
#[TypeScript]
class AdminProductLinkData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public int $linkedProductId,
        public string $linkedProductName,
        public bool $isRequired,
        public int $defaultQuantity,
        public int $sortOrder,
    ) {}

    public static function fromModel(ProductLink $link): self
    {
        return new self(
            id: $link->getKey(),
            type: $link->type->value,
            linkedProductId: $link->linked_product_id,
            linkedProductName: $link->linkedProduct->name,
            isRequired: $link->is_required,
            defaultQuantity: $link->default_quantity,
            sortOrder: $link->sort_order,
        );
    }
}

<?php

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One candidate in the "add a product to this tag" picker.
 *
 * Deliberately not {@see AdminProductRowData}: the picker prints a name and a
 * SKU and nothing else, and that object resolves a thumbnail, the effective
 * price and `isViewableOnStore()` for every row it is handed.
 */
#[TypeScript]
class AdminTagProductOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        /** Null on a product that has never been given one. */
        public ?string $sku,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            id: $product->getKey(),
            name: $product->name,
            sku: $product->sku,
        );
    }
}

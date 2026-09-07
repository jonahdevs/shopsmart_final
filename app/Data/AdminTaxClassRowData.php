<?php

namespace App\Data;

use App\Models\TaxClass;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin tax classes table.
 *
 * `rate` crosses the wire as the string the `decimal:2` cast produces — "16.00"
 * — not as cents and not as a float. A VAT band is a percentage, so none of the
 * money apparatus applies to it: no `money()`, no minor units, and no rounding
 * decision for the client to make. It prints what the database holds.
 *
 * `productCount` is a `withCount` aggregate rather than a loaded relation. It
 * is the number that decides whether the delete is even allowed, so it has to
 * be on every row, and counting in SQL keeps this page's cost flat as the
 * catalog grows.
 */
#[TypeScript]
class AdminTaxClassRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $rate,
        public ?string $description,
        public bool $isActive,
        public int $productCount,
        /** True when TaxSettings points here — this band cannot be deleted. */
        public bool $isStoreDefault,
    ) {}

    public static function fromModel(TaxClass $taxClass, ?int $defaultTaxClassId = null): self
    {
        return new self(
            id: $taxClass->getKey(),
            name: $taxClass->name,
            slug: $taxClass->slug,
            rate: $taxClass->rate,
            description: $taxClass->description,
            isActive: $taxClass->is_active,
            productCount: (int) ($taxClass->getAttribute('products_count') ?? 0),
            isStoreDefault: $defaultTaxClassId !== null && $defaultTaxClassId === $taxClass->getKey(),
        );
    }
}

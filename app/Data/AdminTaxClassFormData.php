<?php

namespace App\Data;

use App\Models\TaxClass;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A tax class as its editor holds it. The create page renders the same
 * component from {@see self::blank()}, so `id` is null until it exists.
 *
 * `rate` stays the cast's string all the way to the input's `value`. Sending a
 * float would round-trip "16.00" as `16` and quietly retype a two-decimal band
 * as a whole number the first time anyone opened the form.
 *
 * `productCount` and `isStoreDefault` are here for the same reason they are on
 * the row: the delete panel has to say why the button will be refused before it
 * is pressed, not after.
 */
#[TypeScript]
class AdminTaxClassFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $slug,
        public string $name,
        public string $rate,
        public ?string $description,
        public bool $isActive,
        public int $productCount,
        public bool $isStoreDefault,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            slug: null,
            name: '',
            rate: '0.00',
            description: null,
            isActive: true,
            productCount: 0,
            isStoreDefault: false,
        );
    }

    public static function fromModel(TaxClass $taxClass, ?int $defaultTaxClassId = null): self
    {
        return new self(
            id: $taxClass->getKey(),
            slug: $taxClass->slug,
            name: $taxClass->name,
            rate: $taxClass->rate,
            description: $taxClass->description,
            isActive: $taxClass->is_active,
            productCount: (int) ($taxClass->getAttribute('products_count') ?? 0),
            isStoreDefault: $defaultTaxClassId !== null && $defaultTaxClassId === $taxClass->getKey(),
        );
    }
}

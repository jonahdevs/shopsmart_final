<?php

namespace App\Data;

use App\Models\Brand;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A manufacturer as the storefront renders it.
 */
#[TypeScript]
class BrandData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $logoUrl,
        public ?string $description,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        $logo = $brand->getFirstMedia('logo');

        return new self(
            id: $brand->getKey(),
            name: $brand->name,
            slug: $brand->slug,
            logoUrl: $logo?->hasGeneratedConversion('thumb') ? $logo->getUrl('thumb') : $logo?->getUrl(),
            description: $brand->description,
        );
    }
}

<?php

namespace App\Data;

use App\Models\Brand;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin brands table.
 *
 * `productCount` is a `withCount` aggregate: it is the number staff check
 * before deleting a brand, and loading the products to count them would be the
 * one query on this page that grows with the catalog.
 */
#[TypeScript]
class AdminBrandRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $websiteUrl,
        public bool $isActive,
        public int $sortOrder,
        public int $productCount,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        return new self(
            id: $brand->getKey(),
            name: $brand->name,
            slug: $brand->slug,
            websiteUrl: $brand->website_url,
            isActive: $brand->is_active,
            sortOrder: $brand->sort_order,
            productCount: (int) ($brand->getAttribute('products_count') ?? 0),
        );
    }
}

<?php

namespace App\Data;

use App\Models\Brand;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A brand as its editor holds it. The create page renders the same component
 * from {@see self::blank()}, so `id` is null until it exists.
 */
#[TypeScript]
class AdminBrandFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $slug,
        public string $name,
        public ?string $description,
        public ?string $websiteUrl,
        public bool $isActive,
        public int $sortOrder,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            slug: null,
            name: '',
            description: null,
            websiteUrl: null,
            isActive: true,
            sortOrder: 0,
            metaTitle: null,
            metaDescription: null,
        );
    }

    public static function fromModel(Brand $brand): self
    {
        return new self(
            id: $brand->getKey(),
            slug: $brand->slug,
            name: $brand->name,
            description: $brand->description,
            websiteUrl: $brand->website_url,
            isActive: $brand->is_active,
            sortOrder: $brand->sort_order,
            metaTitle: $brand->meta_title,
            metaDescription: $brand->meta_description,
        );
    }
}

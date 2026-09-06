<?php

namespace App\Data;

use App\Models\Category;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A category as its editor holds it. The create page renders the same
 * component from {@see self::blank()}, so `id` is null until it exists.
 */
#[TypeScript]
class AdminCategoryFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $slug,
        public string $name,
        public ?int $parentId,
        public ?string $description,
        public ?string $iconSvg,
        public string $status,
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
            parentId: null,
            description: null,
            iconSvg: null,
            status: 'draft',
            sortOrder: 0,
            metaTitle: null,
            metaDescription: null,
        );
    }

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->getKey(),
            slug: $category->slug,
            name: $category->name,
            parentId: $category->parent_id,
            description: $category->description,
            iconSvg: $category->icon_svg,
            status: $category->status->value,
            sortOrder: $category->sort_order,
            metaTitle: $category->meta_title,
            metaDescription: $category->meta_description,
        );
    }
}

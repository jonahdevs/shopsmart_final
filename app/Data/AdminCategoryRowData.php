<?php

namespace App\Data;

use App\Models\Category;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin categories table.
 *
 * `depth` is what makes the flat table read as a tree: the controller walks the
 * roots downward and indents each row by it, so a staff member sees the shape
 * of the taxonomy without the table needing to nest markup.
 *
 * `productCount` is passed in rather than read off a `withCount`, because
 * catalog membership is `primary_category_id` **or** the `category_product`
 * pivot and a product may be in both — two counts added together would report
 * more products than the category actually holds. The controller rolls the two
 * sources up into distinct ids first.
 */
#[TypeScript]
class AdminCategoryRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?int $parentId,
        public int $depth,
        public string $statusLabel,
        public string $statusVariant,
        public int $sortOrder,
        public int $productCount,
        public int $childCount,
    ) {}

    public static function fromModel(Category $category, int $depth, int $productCount, int $childCount): self
    {
        return new self(
            id: $category->getKey(),
            name: $category->name,
            slug: $category->slug,
            parentId: $category->parent_id,
            depth: $depth,
            statusLabel: $category->status->label(),
            statusVariant: $category->status->badgeVariant(),
            sortOrder: $category->sort_order,
            productCount: $productCount,
            childCount: $childCount,
        );
    }
}

<?php

namespace App\Data;

use App\Models\Category;
use App\Support\SvgSanitizer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A catalog category tile. `productCount` is the number of live catalog
 * products in the category itself (see CategoryCounts for how it is selected);
 * it is null when the query did not ask for it.
 */
#[TypeScript]
class CategoryData extends Data
{
    /**
     * @param  list<CategoryData>  $children
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $iconSvg,
        public ?ImageData $image,
        public ?int $productCount,
        public array $children,
    ) {}

    /**
     * @param  list<CategoryData>  $children
     * @param  int|null  $productCount  Overrides a `products_count` selected by the query.
     */
    public static function fromModel(Category $category, array $children = [], ?int $productCount = null): self
    {
        $image = $category->getFirstMedia('image');

        return new self(
            id: $category->getKey(),
            name: $category->name,
            slug: $category->slug,
            description: $category->description,
            // Rendered with v-html by CategoryTiles, so it is reduced to an
            // allow-listed glyph on the way out rather than trusted.
            iconSvg: app(SvgSanitizer::class)->sanitize($category->icon_svg),
            image: $image === null ? null : ImageData::fromMedia($image, $category->name, 'thumb'),
            productCount: $productCount ?? (isset($category->products_count) ? (int) $category->products_count : null),
            children: $children,
        );
    }
}

<?php

namespace App\Data;

use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A product as its editor holds it.
 *
 * Money is in **major units** here and nowhere else in app/Data. Every other
 * object sends integer cents plus a preformatted string, because the client
 * displays money; this one populates inputs a staff member types whole KES
 * into, and {@see ProductRequest} converts what comes
 * back with {@see Money::toMinor()}. Sending cents to a form input would mean
 * the browser dividing by 100 somewhere, which is the exact arithmetic the
 * money rule exists to keep out of client code.
 *
 * `salePrice` is what the customer pays and `price` the struck-through
 * original — see {@see Product::effectivePriceCents()}.
 *
 * The create page renders the same component from an empty instance, so every
 * field is nullable and `id` is null for a product that does not exist yet.
 */
#[TypeScript]
class AdminProductFormData extends Data
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<string>  $tags
     * @param  list<AdminProductVariantData>  $variants
     * @param  list<AdminProductLinkData>  $links
     * @param  list<AdminProductMediaData>  $media
     */
    public function __construct(
        public ?int $id,
        public ?string $slug,
        public string $name,
        public ?string $sku,
        public ?string $modelNumber,
        public string $type,
        public string $status,
        public string $visibility,
        /** `YYYY-MM-DDTHH:MM`, the value a datetime-local input takes. */
        public ?string $publishedAt,
        public ?int $brandId,
        public ?int $primaryCategoryId,
        public array $categoryIds,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $technicalSpecification,
        public ?float $price,
        public ?float $salePrice,
        public ?float $costPrice,
        public bool $isTaxable,
        public ?int $taxClassId,
        public bool $isVirtual,
        public bool $requiresShipping,
        public string $stockStatus,
        public ?int $stockQuantity,
        public bool $allowBackorder,
        public ?int $lowStockThreshold,
        public ?int $minOrderQuantity,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public int $sortOrder,
        public array $tags,
        public array $variants,
        public array $links,
        public array $media,
    ) {}

    /**
     * The blank product the create page opens on.
     *
     * The defaults match the products table's column defaults rather than being
     * invented here, so a product created through the admin and one created by
     * a seeder start in the same state.
     */
    public static function blank(): self
    {
        return new self(
            id: null,
            slug: null,
            name: '',
            sku: null,
            modelNumber: null,
            type: 'simple',
            status: 'draft',
            visibility: 'visible',
            publishedAt: null,
            brandId: null,
            primaryCategoryId: null,
            categoryIds: [],
            shortDescription: null,
            description: null,
            technicalSpecification: null,
            price: null,
            salePrice: null,
            costPrice: null,
            isTaxable: true,
            taxClassId: null,
            isVirtual: false,
            requiresShipping: true,
            stockStatus: 'in_stock',
            stockQuantity: null,
            allowBackorder: false,
            lowStockThreshold: null,
            minOrderQuantity: null,
            metaTitle: null,
            metaDescription: null,
            sortOrder: 0,
            tags: [],
            variants: [],
            links: [],
            media: [],
        );
    }

    /**
     * Expects `variants.attributeValues`, `links.linkedProduct`, `tags` and
     * `media` to be loaded; the editor loads all four in one go.
     */
    public static function fromModel(Product $product): self
    {
        $money = app(Money::class);

        return new self(
            id: $product->getKey(),
            slug: $product->slug,
            name: $product->name,
            sku: $product->sku,
            modelNumber: $product->model_number,
            type: $product->type->value,
            status: $product->status->value,
            visibility: $product->visibility->value,
            publishedAt: $product->published_at?->format('Y-m-d\TH:i'),
            brandId: $product->brand_id,
            primaryCategoryId: $product->primary_category_id,
            categoryIds: array_values(
                $product->categories->map(fn ($category): int => (int) $category->getKey())->all(),
            ),
            shortDescription: $product->short_description,
            description: $product->description,
            technicalSpecification: $product->technical_specification,
            price: $product->price === null ? null : $money->toMajor($product->price),
            salePrice: $product->sale_price === null ? null : $money->toMajor($product->sale_price),
            costPrice: $product->cost_price === null ? null : $money->toMajor($product->cost_price),
            isTaxable: $product->is_taxable,
            taxClassId: $product->tax_class_id,
            isVirtual: $product->is_virtual,
            requiresShipping: $product->requires_shipping,
            stockStatus: $product->stock_status->value,
            stockQuantity: $product->stock_quantity,
            allowBackorder: $product->allow_backorder,
            lowStockThreshold: $product->low_stock_threshold,
            minOrderQuantity: $product->min_order_quantity,
            metaTitle: $product->meta_title,
            metaDescription: $product->meta_description,
            sortOrder: $product->sort_order,
            // spatie/laravel-tags types the relation as Model rather than Tag,
            // so the name is read through getAttribute() instead of ->name.
            tags: array_values($product->tags
                ->map(fn (Model $tag): string => (string) $tag->getAttribute('name'))
                ->all()),
            variants: array_values($product->variants
                ->map(fn ($variant): AdminProductVariantData => AdminProductVariantData::fromModel($variant))
                ->all()),
            links: array_values($product->links
                ->map(fn ($link): AdminProductLinkData => AdminProductLinkData::fromModel($link))
                ->all()),
            media: array_values($product->getMedia('images')
                ->map(fn ($media): AdminProductMediaData => AdminProductMediaData::fromMedia($media))
                ->all()),
        );
    }
}

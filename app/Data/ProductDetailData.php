<?php

namespace App\Data;

use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The full product page payload: the card fields, the long-form copy, the
 * gallery, the specification table, and — for a variable product — every
 * purchasable variant plus the axes the shopper picks along.
 *
 * Assembling this expects the caller to have eager-loaded `brand`,
 * `primaryCategory`, `media`, `productAttributes.attribute` and, for variable
 * products, `variants.attributeValues.attribute`.
 */
#[TypeScript]
class ProductDetailData extends Data
{
    /**
     * @param  list<BreadcrumbData>  $breadcrumbs
     * @param  list<ImageData>  $images
     * @param  list<SpecificationData>  $specifications
     * @param  list<ProductVariantData>  $variants
     * @param  list<VariationAttributeData>  $variationAttributes
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $sku,
        public ?string $modelNumber,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $technicalSpecification,
        public ?BrandData $brand,
        public ?CategoryData $primaryCategory,
        public array $breadcrumbs,
        public array $images,
        public ?int $priceCents,
        public ?int $salePriceCents,
        public ?int $effectivePriceCents,
        public ?string $priceFormatted,
        public ?string $salePriceFormatted,
        public ?string $effectivePriceFormatted,
        public ?int $discountPercent,
        public bool $isOnSale,
        public ?float $ratingAverage,
        public int $ratingCount,
        public bool $inStock,
        public StockStatus $stockStatus,
        public ?int $stockQuantity,
        public bool $allowBackorder,
        public int $minOrderQuantity,
        public ProductType $type,
        public bool $isVariable,
        public bool $requiresOptions,
        public array $specifications,
        public array $variants,
        public array $variationAttributes,
        public ?int $defaultVariantId,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}

    /**
     * @param  list<BreadcrumbData>  $breadcrumbs
     */
    public static function fromModel(Product $product, array $breadcrumbs = []): self
    {
        $effective = $product->effectivePriceCents();
        $card = ProductCardData::fromModel($product);

        return new self(
            id: $product->getKey(),
            name: $product->name,
            slug: $product->slug,
            sku: $product->sku,
            modelNumber: $product->model_number,
            shortDescription: $product->short_description,
            description: $product->description,
            technicalSpecification: $product->technical_specification,
            brand: $product->brand === null ? null : BrandData::fromModel($product->brand),
            primaryCategory: $product->primaryCategory === null ? null : CategoryData::fromModel($product->primaryCategory),
            breadcrumbs: $breadcrumbs,
            images: self::gallery($product),
            priceCents: $product->price,
            salePriceCents: $product->sale_price,
            effectivePriceCents: $effective,
            priceFormatted: $card->priceFormatted,
            salePriceFormatted: $card->salePriceFormatted,
            effectivePriceFormatted: $card->effectivePriceFormatted,
            discountPercent: $product->discountPercent(),
            isOnSale: $product->isOnSale(),
            ratingAverage: $card->ratingAverage,
            ratingCount: $card->ratingCount,
            inStock: $product->isInStock(),
            stockStatus: $product->stock_status,
            stockQuantity: $product->stock_quantity,
            allowBackorder: $product->allow_backorder,
            minOrderQuantity: max(1, (int) ($product->min_order_quantity ?? 1)),
            type: $product->type,
            isVariable: $product->hasVariants(),
            requiresOptions: $card->requiresOptions,
            specifications: self::specifications($product),
            variants: self::variants($product),
            variationAttributes: self::variationAttributes($product),
            defaultVariantId: $product->default_variant_id,
            metaTitle: $product->meta_title,
            metaDescription: $product->meta_description,
        );
    }

    /**
     * The whole gallery, cover first, each with its card, zoom and blur-up
     * renditions resolved.
     *
     * @return list<ImageData>
     */
    private static function gallery(Product $product): array
    {
        return array_values($product->getMedia('images')
            ->sortByDesc(fn (Media $media): bool => (bool) $media->getCustomProperty('is_cover', false))
            ->map(fn (Media $media): ImageData => ImageData::fromMedia($media, $product->name))
            ->all());
    }

    /**
     * The visible specification rows, with each stored value slug swapped for
     * its human label in a single lookup query.
     *
     * @return list<SpecificationData>
     */
    private static function specifications(Product $product): array
    {
        if (! $product->relationLoaded('productAttributes')) {
            return [];
        }

        $rows = $product->productAttributes
            ->filter(fn (ProductAttribute $row): bool => $row->is_visible)
            ->sortBy('sort_order');

        if ($rows->isEmpty()) {
            return [];
        }

        $labels = self::labelsBySlug($rows);

        return array_values($rows
            ->map(fn (ProductAttribute $row): SpecificationData => new SpecificationData(
                name: $row->attribute->name,
                values: array_values(array_map(
                    fn (string $slug): string => $labels[$row->attribute_id][$slug] ?? $slug,
                    $row->values ?? [],
                )),
            ))
            ->all());
    }

    /**
     * @param  Collection<int, ProductAttribute>  $rows
     * @return array<int, array<string, string>> attribute id => [value slug => label]
     */
    private static function labelsBySlug(Collection $rows): array
    {
        /** @var list<string> $slugs */
        $slugs = array_values($rows->flatMap(fn (ProductAttribute $row): array => $row->values ?? [])->unique()->values()->all());

        if ($slugs === []) {
            return [];
        }

        /** @var array<int, array<string, string>> $labels */
        $labels = [];

        $values = AttributeValue::query()
            ->whereIn('attribute_id', $rows->pluck('attribute_id')->unique()->all())
            ->whereIn('slug', $slugs)
            ->get(['attribute_id', 'slug', 'label']);

        foreach ($values as $value) {
            $labels[$value->attribute_id][$value->slug] = $value->label;
        }

        return $labels;
    }

    /**
     * @return list<ProductVariantData>
     */
    private static function variants(Product $product): array
    {
        if (! $product->hasVariants() || ! $product->relationLoaded('variants')) {
            return [];
        }

        return array_values($product->variants
            ->map(fn (ProductVariant $variant): ProductVariantData => ProductVariantData::fromModel($variant))
            ->all());
    }

    /**
     * The axes a variable product varies along, derived from the values its
     * variants actually use rather than from the product's declared attribute
     * list — an axis with no variant behind it can never be chosen.
     *
     * @return list<VariationAttributeData>
     */
    private static function variationAttributes(Product $product): array
    {
        if (! $product->hasVariants() || ! $product->relationLoaded('variants')) {
            return [];
        }

        return array_values($product->variants
            ->flatMap(fn (ProductVariant $variant): iterable => $variant->attributeValues)
            ->unique(fn (AttributeValue $value): int => $value->getKey())
            ->groupBy(fn (AttributeValue $value): int => $value->attribute_id)
            ->sortBy(fn (Collection $group): int => $group->first()->attribute->sort_order)
            ->map(function (Collection $group): VariationAttributeData {
                $attribute = $group->first()->attribute;

                return new VariationAttributeData(
                    id: $attribute->getKey(),
                    name: $attribute->name,
                    slug: $attribute->slug,
                    type: $attribute->type,
                    values: array_values($group
                        ->sortBy('sort_order')
                        ->map(fn (AttributeValue $value): AttributeValueData => AttributeValueData::fromModel($value))
                        ->all()),
                );
            })
            ->all());
    }
}

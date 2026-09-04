<?php

namespace App\Data;

use App\Enums\StockStatus;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One purchasable combination of a variable product ("Red / XL").
 *
 * `attributeValueIds` is what the client matches a shopper's selection
 * against — comparing ids avoids re-deriving slugs on both sides of the wire.
 */
#[TypeScript]
class ProductVariantData extends Data
{
    /**
     * @param  list<int>  $attributeValueIds
     */
    public function __construct(
        public int $id,
        public string $sku,
        public string $optionLabel,
        public ?int $priceCents,
        public ?int $salePriceCents,
        public ?int $effectivePriceCents,
        public ?string $priceFormatted,
        public ?string $salePriceFormatted,
        public ?string $effectivePriceFormatted,
        public ?int $discountPercent,
        public bool $isOnSale,
        public bool $inStock,
        public StockStatus $stockStatus,
        public ?int $stockQuantity,
        public array $attributeValueIds,
        public ?ImageData $image,
    ) {}

    public static function fromModel(ProductVariant $variant): self
    {
        $effective = $variant->effectivePriceCents();
        $image = $variant->getFirstMedia('image');

        return new self(
            id: $variant->getKey(),
            sku: $variant->sku,
            optionLabel: $variant->optionLabel(),
            priceCents: $variant->price,
            salePriceCents: $variant->sale_price,
            effectivePriceCents: $effective,
            priceFormatted: $variant->price === null ? null : money($variant->price),
            salePriceFormatted: $variant->sale_price === null ? null : money($variant->sale_price),
            effectivePriceFormatted: $effective === null ? null : money($effective),
            discountPercent: self::discountPercent($variant),
            isOnSale: $variant->isOnSale(),
            inStock: $variant->isInStock(),
            stockStatus: $variant->stock_status,
            stockQuantity: $variant->stock_quantity,
            attributeValueIds: array_values($variant->attributeValues
                ->map(fn (AttributeValue $value): int => $value->getKey())
                ->all()),
            image: $image === null ? null : ImageData::fromMedia($image, $variant->optionLabel()),
        );
    }

    /**
     * ProductVariant has no discountPercent() of its own; the arithmetic is the
     * same as Product's because sale_price carries the same meaning on both.
     */
    private static function discountPercent(ProductVariant $variant): ?int
    {
        if (! $variant->isOnSale() || $variant->price === null || $variant->price === 0) {
            return null;
        }

        return (int) round((($variant->price - (int) $variant->sale_price) / $variant->price) * 100);
    }
}

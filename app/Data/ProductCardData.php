<?php

namespace App\Data;

use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything a grid tile or carousel slide renders for one product.
 *
 * Prices arrive both as integer cents (for sorting and comparisons) and as
 * strings already run through {@see money()}, so no client ever has to know
 * the store's currency symbol, separators or decimal count. A null price is
 * price-on-application and stays null in both forms.
 */
#[TypeScript]
class ProductCardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $sku,
        public ?string $brandName,
        public ?ImageData $image,
        public ?int $priceCents,
        public ?int $salePriceCents,
        /** What the customer actually pays: the sale price when there is one. */
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
        public ProductType $type,
        public bool $isVariable,
        /**
         * True when the shopper must choose something before this can go in a
         * cart, so the tile links through to the product page instead of
         * offering a direct add-to-cart.
         */
        public bool $requiresOptions,
    ) {}

    public static function fromModel(Product $product): self
    {
        $effective = $product->effectivePriceCents();

        return new self(
            id: $product->getKey(),
            name: $product->name,
            slug: $product->slug,
            sku: $product->sku,
            brandName: $product->brand?->name,
            image: self::cover($product),
            priceCents: $product->price,
            salePriceCents: $product->sale_price,
            effectivePriceCents: $effective,
            priceFormatted: $product->price === null ? null : money($product->price),
            salePriceFormatted: $product->sale_price === null ? null : money($product->sale_price),
            effectivePriceFormatted: $effective === null ? null : money($effective),
            discountPercent: $product->discountPercent(),
            isOnSale: $product->isOnSale(),
            ratingAverage: $product->reviews_avg_rating === null ? null : round((float) $product->reviews_avg_rating, 2),
            ratingCount: (int) ($product->reviews_count ?? 0),
            inStock: $product->isInStock(),
            stockStatus: $product->stock_status,
            type: $product->type,
            isVariable: $product->hasVariants(),
            requiresOptions: self::requiresOptions($product),
        );
    }

    /**
     * The image marked as the cover, falling back to the first one uploaded.
     * Reads the already-loaded media collection, so an eager-loaded listing
     * costs no extra query.
     */
    public static function cover(Product $product): ?ImageData
    {
        $media = $product->getMedia('images');

        $cover = $media->first(fn (Media $item): bool => (bool) $item->getCustomProperty('is_cover', false))
            ?? $media->first();

        return $cover === null ? null : ImageData::fromMedia($cover, $product->name);
    }

    /**
     * Variable products price through a variant, and grouped and bundled
     * products through their children, so all three need the product page.
     */
    private static function requiresOptions(Product $product): bool
    {
        return in_array($product->type, [ProductType::Variable, ProductType::Grouped, ProductType::Bundled], true);
    }
}

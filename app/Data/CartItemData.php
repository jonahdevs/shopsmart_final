<?php

namespace App\Data;

use App\Models\Product;
use App\Models\ProductVariant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of the rendered cart.
 *
 * `unitPriceCents` is the price captured when the line was opened, not a live
 * catalog lookup. `currentUnitPriceCents` is the live one, so the page can say
 * "this has gone up" instead of quietly changing the number under the shopper.
 *
 * Money crosses the wire twice, cents and formatted, so no client formats
 * currency. `key` is the line's identity in the session cart — the product id,
 * or "productId|variantId" when a variant was chosen — and is what a v-for
 * should key on. Mutations post `productId` / `variantId` rather than the key,
 * so the server can validate them.
 */
#[TypeScript]
class CartItemData extends Data
{
    public function __construct(
        public string $key,
        public int $productId,
        public ?int $variantId,
        public string $name,
        public string $slug,
        public ?string $sku,
        public ?string $brandName,
        /** "Red / XL" for a variant line, null for a simple one. */
        public ?string $optionLabel,
        public ?ImageData $image,
        public int $quantity,
        public int $unitPriceCents,
        public string $unitPriceFormatted,
        public int $lineTotalCents,
        public string $lineTotalFormatted,
        public int $currentUnitPriceCents,
        public string $currentUnitPriceFormatted,
        public bool $priceChanged,
        public bool $inStock,
        /** How many the shopper may take; null when stock is untracked or backorderable. */
        public ?int $maxQuantity,
    ) {}

    public static function fromLine(
        string $key,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        int $unitPriceCents,
        int $currentUnitPriceCents,
        ?int $maxQuantity,
    ): self {
        $lineTotal = $unitPriceCents * $quantity;

        return new self(
            key: $key,
            productId: $product->getKey(),
            variantId: $variant?->getKey(),
            name: $product->name,
            slug: $product->slug,
            // `??` is null-safe over the whole chain, so a null variant falls
            // through to the product's SKU without a nullsafe operator.
            sku: $variant->sku ?? $product->sku,
            brandName: $product->brand?->name,
            optionLabel: $variant === null ? null : ($variant->optionLabel() ?: null),
            image: self::image($product, $variant),
            quantity: $quantity,
            unitPriceCents: $unitPriceCents,
            unitPriceFormatted: money($unitPriceCents),
            lineTotalCents: $lineTotal,
            lineTotalFormatted: money($lineTotal),
            currentUnitPriceCents: $currentUnitPriceCents,
            currentUnitPriceFormatted: money($currentUnitPriceCents),
            priceChanged: $currentUnitPriceCents !== $unitPriceCents,
            inStock: $variant === null ? $product->isInStock() : $variant->isInStock(),
            maxQuantity: $maxQuantity,
        );
    }

    /**
     * A variant's own photo when it has one, otherwise the product's cover.
     * Both media collections are eager-loaded by the caller, so neither branch
     * costs a query.
     */
    private static function image(Product $product, ?ProductVariant $variant): ?ImageData
    {
        $variantImage = $variant?->getFirstMedia('image');

        if ($variantImage !== null) {
            return ImageData::fromMedia($variantImage, $product->name);
        }

        return ProductCardData::cover($product);
    }
}

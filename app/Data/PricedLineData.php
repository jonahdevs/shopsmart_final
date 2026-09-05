<?php

namespace App\Data;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line, priced.
 *
 * The same shape serves the checkout summary and the placed order, from two
 * different sources: {@see fromLine()} builds it off the live catalog while the
 * shopper is still deciding, {@see fromOrderItem()} rebuilds it from the
 * snapshot frozen at placement. One shape means one Vue component, and means a
 * shopper's order page cannot drift from the page they bought on.
 *
 * `discountCents` is this line's share of an order-level coupon, allocated
 * pro-rata. `taxCents` is computed on the line total AFTER that share is taken
 * off, which is what stops a coupon from leaving the tax it saved behind.
 *
 * Money crosses the wire twice, cents and formatted, so no client formats
 * currency.
 */
#[TypeScript]
class PricedLineData extends Data
{
    public function __construct(
        public ?int $productId,
        public ?int $variantId,
        public string $name,
        public ?string $slug,
        public ?string $sku,
        public ?string $brandName,
        /** "Red / XL" for a variant line, null for a simple one. */
        public ?string $optionLabel,
        public ?ImageData $image,
        public int $quantity,
        public int $unitPriceCents,
        public string $unitPriceFormatted,
        /** Unit price times quantity, before any discount. */
        public int $subtotalCents,
        public string $subtotalFormatted,
        public int $discountCents,
        public string $discountFormatted,
        /** The VAT percentage applied to this line, as a number. */
        public float $taxRate,
        public int $taxCents,
        public string $taxFormatted,
        /** What this line contributes to the order total. */
        public int $totalCents,
        public string $totalFormatted,
    ) {}

    public static function fromLine(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        int $unitPriceCents,
        int $discountCents,
        float $taxRate,
        int $taxCents,
        int $totalCents,
    ): self {
        $subtotal = $unitPriceCents * $quantity;

        return new self(
            productId: $product->getKey(),
            variantId: $variant?->getKey(),
            name: $product->name,
            slug: $product->slug,
            sku: $variant->sku ?? $product->sku,
            brandName: $product->brand?->name,
            optionLabel: $variant === null ? null : ($variant->optionLabel() ?: null),
            image: self::image($product, $variant),
            quantity: $quantity,
            unitPriceCents: $unitPriceCents,
            unitPriceFormatted: money($unitPriceCents),
            subtotalCents: $subtotal,
            subtotalFormatted: money($subtotal),
            discountCents: $discountCents,
            discountFormatted: money($discountCents),
            taxRate: $taxRate,
            taxCents: $taxCents,
            taxFormatted: money($taxCents),
            totalCents: $totalCents,
            totalFormatted: money($totalCents),
        );
    }

    /**
     * Rebuild a line from what was frozen onto the order.
     *
     * Reads the snapshot, never the product relation: the product may since
     * have been renamed, repriced or deleted, and none of that may change what
     * this order says was sold.
     */
    public static function fromOrderItem(OrderItem $item): self
    {
        $snapshot = $item->product_snapshot;

        /** @var array<string, mixed>|null $image */
        $image = is_array($snapshot['image'] ?? null) ? $snapshot['image'] : null;

        return new self(
            productId: $item->product_id,
            variantId: $item->product_variant_id,
            name: $item->name,
            slug: is_string($snapshot['slug'] ?? null) ? $snapshot['slug'] : null,
            sku: $item->sku,
            brandName: is_string($snapshot['brandName'] ?? null) ? $snapshot['brandName'] : null,
            optionLabel: $item->option_label,
            image: $image === null ? null : ImageData::from($image),
            quantity: $item->quantity,
            unitPriceCents: $item->unit_price_cents,
            unitPriceFormatted: money($item->unit_price_cents),
            subtotalCents: $item->subtotal_cents,
            subtotalFormatted: money($item->subtotal_cents),
            discountCents: $item->discount_cents,
            discountFormatted: money($item->discount_cents),
            taxRate: (float) $item->tax_rate,
            taxCents: $item->tax_cents,
            taxFormatted: money($item->tax_cents),
            totalCents: $item->total_cents,
            totalFormatted: money($item->total_cents),
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

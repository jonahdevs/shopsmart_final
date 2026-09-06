<?php

namespace App\Data;

use App\Enums\StockStatus;
use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin products table.
 *
 * Deliberately not {@see ProductCardData}: that object resolves every image
 * rendition and the review aggregates, which a storefront tile needs and a
 * table of fifty rows does not. This carries only what the table prints.
 *
 * `variantCount` arrives as a `withCount` aggregate rather than a loaded
 * relation — the one number here that would otherwise grow with the catalog.
 */
#[TypeScript]
class AdminProductRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $sku,
        public string $statusLabel,
        public string $statusVariant,
        public string $visibilityLabel,
        public string $typeLabel,
        public ?string $brandName,
        public ?string $categoryName,
        /** Effective price in cents; null is price-on-application. */
        public ?int $priceCents,
        /** Preformatted for display — clients never format currency. */
        public ?string $priceFormatted,
        public bool $isOnSale,
        public string $stockStatusLabel,
        public string $stockStatusVariant,
        public ?int $stockQuantity,
        public int $variantCount,
        /** True for a soft-deleted product, which the storefront no longer shows. */
        public bool $isDeleted,
        public string $updatedAt,
    ) {}

    public static function fromModel(Product $product): self
    {
        $priceCents = $product->effectivePriceCents();

        return new self(
            id: $product->getKey(),
            name: $product->name,
            slug: $product->slug,
            sku: $product->sku,
            statusLabel: $product->status->label(),
            statusVariant: $product->status->badgeVariant(),
            visibilityLabel: $product->visibility->label(),
            typeLabel: $product->type->label(),
            brandName: $product->brand?->name,
            categoryName: $product->primaryCategory?->name,
            priceCents: $priceCents,
            priceFormatted: $priceCents === null ? null : money($priceCents),
            isOnSale: $product->isOnSale(),
            stockStatusLabel: $product->stock_status->label(),
            stockStatusVariant: self::stockVariant($product->stock_status),
            stockQuantity: $product->stock_quantity,
            // Set by `withCount('variants')`; absent when a caller hands over a
            // plain model, which the table never does.
            variantCount: (int) ($product->getAttribute('variants_count') ?? 0),
            isDeleted: $product->trashed(),
            updatedAt: ($product->updated_at ?? $product->freshTimestamp())->toIso8601String(),
        );
    }

    /**
     * The Badge variant a stock state wears.
     *
     * Lives here rather than on {@see StockStatus} because the storefront never
     * badges stock — it prints "In stock" as prose — so the mapping is an
     * admin-table concern and adding it to the enum would put a rendering
     * decision somewhere the storefront would inherit it.
     */
    private static function stockVariant(StockStatus $status): string
    {
        return match ($status) {
            StockStatus::InStock => 'default',
            StockStatus::Backorder => 'secondary',
            StockStatus::OutOfStock => 'destructive',
        };
    }
}

<?php

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The badge a staff member sees on a storefront page the public cannot reach.
 *
 * A catalog manager needs to see how a product will look on the shop floor
 * before it is published, and the only honest way to show that is the real
 * storefront page. So `product.show` renders one for a product that is not
 * live — but ONLY for a signed-in staff member holding a products permission,
 * and only with this object attached.
 *
 * That makes the object itself the switch. Its presence is what turns the
 * banner on, what strips the buy controls, and what tells the head not to
 * offer the page to a crawler; a shopper never receives it, so none of that
 * treatment can leak onto a live listing. A page rendering without it is the
 * real page, byte for byte.
 */
#[TypeScript]
class ProductPreviewData extends Data
{
    public function __construct(
        /** The product's own state, e.g. "Draft" — the banner names it. */
        public string $statusLabel,
        public string $visibilityLabel,
        /** One line saying why the public cannot see this page. */
        public string $reason,
        /** Back to the editor, which is where a preview normally ends. */
        public string $editUrl,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            statusLabel: $product->status->label(),
            visibilityLabel: $product->visibility->label(),
            reason: self::reason($product),
            editUrl: route('admin.products.edit', $product),
        );
    }

    /**
     * Why this page is not public, in the terms the editor uses.
     *
     * Two separate reasons, and a staff member has to be told which one they
     * are looking at: a draft becomes public by publishing it, a published
     * product set to Hidden does not. Naming the wrong one would send them to
     * change the wrong field.
     */
    private static function reason(Product $product): string
    {
        if (! $product->isPublished()) {
            return __('Shoppers cannot open this page — the product is :status.', [
                'status' => mb_strtolower($product->status->label()),
            ]);
        }

        return __('Shoppers cannot open this page — the product is hidden from the storefront.');
    }
}

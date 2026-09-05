<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The whole rendered cart.
 *
 * The subtotal is the sum of the captured line prices, so it matches what the
 * shopper is looking at line by line. Delivery, tax and coupons are Phase 4's
 * problem and deliberately absent — this object is the cart, not the order.
 */
#[TypeScript]
class CartData extends Data
{
    /**
     * @param  list<CartItemData>  $items
     */
    public function __construct(
        public array $items,
        /** Total units across every line — the badge number in the header. */
        public int $itemCount,
        /** Distinct lines, which is what the cart table renders. */
        public int $lineCount,
        public int $subtotalCents,
        public string $subtotalFormatted,
        public bool $isEmpty,
        /** True when any line's captured price no longer matches the catalog. */
        public bool $hasPriceChanges,
    ) {}

    /**
     * @param  list<CartItemData>  $items
     */
    public static function fromItems(array $items): self
    {
        $subtotal = array_sum(array_map(
            fn (CartItemData $item): int => $item->lineTotalCents,
            $items,
        ));

        return new self(
            items: $items,
            itemCount: array_sum(array_map(
                fn (CartItemData $item): int => $item->quantity,
                $items,
            )),
            lineCount: count($items),
            subtotalCents: $subtotal,
            subtotalFormatted: money($subtotal),
            isEmpty: $items === [],
            hasPriceChanges: array_any(
                $items,
                fn (CartItemData $item): bool => $item->priceChanged,
            ),
        );
    }

    /**
     * An empty cart. Named `blank` rather than `empty` because
     * {@see Data::empty()} is a different thing entirely — a skeleton array of
     * the object's own shape.
     */
    public static function blank(): self
    {
        return self::fromItems([]);
    }
}

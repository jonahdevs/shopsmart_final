<?php

namespace App\Data;

use App\Support\CheckoutPricer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A priced cart, ready to become an order.
 *
 * Produced by {@see CheckoutPricer} from the live catalog, never
 * from the prices captured in the cart. The page renders it and posts back
 * `totals.totalCents`; if the quote has moved by the time the order is placed,
 * placement refuses rather than charging a number the shopper never saw.
 *
 * `blockers` are the reasons this cart cannot be ordered as it stands — a line
 * that went out of stock, a subtotal under the store minimum. An empty list
 * means the Place Order button is live.
 */
#[TypeScript]
class CheckoutQuoteData extends Data
{
    /**
     * @param  list<PricedLineData>  $lines
     * @param  list<string>  $blockers
     */
    public function __construct(
        public array $lines,
        public OrderTotalsData $totals,
        public int $minOrderValueCents,
        public string $minOrderValueFormatted,
        public bool $meetsMinimum,
        /** How much more the shopper must spend for free delivery; null when it is already free or not applicable. */
        public ?int $freeShippingRemainingCents,
        public ?string $freeShippingRemainingFormatted,
        public array $blockers,
    ) {}

    public function isPlaceable(): bool
    {
        return $this->lines !== [] && $this->blockers === [];
    }
}

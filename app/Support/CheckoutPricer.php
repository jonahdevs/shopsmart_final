<?php

namespace App\Support;

use App\Data\CheckoutQuoteData;
use App\Data\OrderTotalsData;
use App\Data\PricedLineData;
use App\Enums\DeliveryMethod;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use App\Settings\CheckoutSettings;
use App\Settings\ShippingSettings;
use App\Settings\TaxSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The single authority on what a cart costs.
 *
 * Every number the shopper is shown at checkout, and every number written onto
 * the placed order, comes out of {@see quote()}. Nothing else in the
 * application adds money up — that is what keeps the page and the order from
 * ever disagreeing, and it is why the discount/tax interaction below only has
 * to be right once.
 *
 * Order of operations, and why:
 *
 *   subtotal -> discount -> shipping -> tax -> total
 *
 * The discount is spread across the lines pro-rata before tax is worked out, so
 * tax is charged on what the shopper actually pays. Doing it the other way —
 * taxing the full subtotal and then discounting — quietly keeps the tax on the
 * money the coupon took off, which overcharges on a tax-exclusive store and
 * makes the per-line tax on the order wrong on any store.
 *
 * Prices are read live from the catalog. `cart_items.unit_price_cents` is a
 * record of what the shopper was shown, never an authority for what they are
 * charged.
 */
class CheckoutPricer
{
    /** Resolved once per instance; null until {@see defaultTaxClass()} has looked. */
    private ?TaxClass $defaultTaxClass = null;

    private bool $defaultTaxClassResolved = false;

    public function __construct(
        private TaxSettings $taxSettings,
        private ShippingSettings $shippingSettings,
        private CheckoutSettings $checkoutSettings,
    ) {}

    /**
     * Price a set of cart lines.
     *
     * Two queries however many lines there are. A line whose product has since
     * been deleted or hidden is dropped, and a line that can no longer be bought
     * in the quantity asked for raises a blocker rather than being silently
     * trimmed — the shopper decides what to do about it, not the pricer.
     *
     * @param  array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}>  $lines
     */
    public function quote(array $lines, ?Coupon $coupon = null, DeliveryMethod $delivery = DeliveryMethod::Delivery): CheckoutQuoteData
    {
        if ($lines === []) {
            return $this->emptyQuote($delivery);
        }

        [$products, $variants] = $this->loadCatalog($lines);

        $priced = [];
        $blockers = [];

        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);

            if ($product === null) {
                continue;
            }

            $variant = $line['variant_id'] === null ? null : $variants->get($line['variant_id']);

            if ($line['variant_id'] !== null && $variant === null) {
                continue;
            }

            // The variant's price and tax class both fall back to the parent
            // product; setting the relation keeps that from lazy-loading per
            // line, which would throw under preventLazyLoading.
            $variant?->setRelation('product', $product);

            $unitPrice = $variant?->effectivePriceCents() ?? $product->effectivePriceCents();

            if ($unitPrice === null) {
                $blockers[] = __(':name is not currently priced and cannot be ordered.', ['name' => $product->name]);

                continue;
            }

            $available = $this->availableQuantity($product, $variant);

            if ($available !== null && $available < $line['quantity']) {
                $blockers[] = $available === 0
                    ? __(':name has sold out.', ['name' => $product->name])
                    : __('Only :count of :name are left.', ['count' => $available, 'name' => $product->name]);
            }

            $priced[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $line['quantity'],
                'unit_price_cents' => $unitPrice,
                'subtotal_cents' => $unitPrice * $line['quantity'],
                'tax_rate' => $this->rateForProduct($product),
            ];
        }

        $subtotal = array_sum(array_column($priced, 'subtotal_cents'));
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $shares = $this->allocate($discount, array_column($priced, 'subtotal_cents'));
        $shipping = $this->shippingCents($delivery, $subtotal - $discount);

        $lineData = [];
        $tax = 0;

        foreach ($priced as $index => $line) {
            $share = $shares[$index] ?? 0;
            $net = $line['subtotal_cents'] - $share;
            $lineTax = $this->taxFor($net, $line['tax_rate']);
            $tax += $lineTax;

            $lineData[] = PricedLineData::fromLine(
                product: $line['product'],
                variant: $line['variant'],
                quantity: $line['quantity'],
                unitPriceCents: $line['unit_price_cents'],
                discountCents: $share,
                taxRate: $line['tax_rate'],
                taxCents: $lineTax,
                totalCents: $this->pricesIncludeTax() ? $net : $net + $lineTax,
            );
        }

        // Delivery is taxed on the same terms as the goods, at the store's
        // default rate — a delivery charge is a taxable supply in Kenya.
        $tax += $this->taxFor($shipping, $this->defaultRate());

        $total = $this->pricesIncludeTax()
            ? $subtotal - $discount + $shipping
            : $subtotal - $discount + $shipping + $tax;

        $minimum = $this->checkoutSettings->min_order_value_cents;
        $meetsMinimum = $subtotal >= $minimum;

        if (! $meetsMinimum) {
            $blockers[] = __('Orders start at :amount.', ['amount' => money($minimum)]);
        }

        $freeShippingRemaining = $this->freeShippingRemaining($delivery, $subtotal - $discount);

        return new CheckoutQuoteData(
            lines: $lineData,
            totals: new OrderTotalsData(
                subtotalCents: $subtotal,
                subtotalFormatted: money($subtotal),
                discountCents: $discount,
                discountFormatted: money($discount),
                shippingCents: $shipping,
                shippingFormatted: money($shipping),
                taxCents: $tax,
                taxFormatted: money($tax),
                totalCents: $total,
                totalFormatted: money($total),
                pricesIncludeTax: $this->pricesIncludeTax(),
                taxLabel: OrderTotalsData::label($this->pricesIncludeTax()),
                couponCode: $coupon?->code,
                deliveryMethod: $delivery,
                shippingIsFree: $shipping === 0 && $delivery === DeliveryMethod::Delivery,
            ),
            minOrderValueCents: $minimum,
            minOrderValueFormatted: money($minimum),
            meetsMinimum: $meetsMinimum,
            freeShippingRemainingCents: $freeShippingRemaining,
            freeShippingRemainingFormatted: $freeShippingRemaining === null ? null : money($freeShippingRemaining),
            blockers: array_values(array_unique($blockers)),
        );
    }

    // ==================================================
    // CATALOG
    // ==================================================

    /**
     * @param  array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}>  $lines
     * @return array{0: EloquentCollection<int, Product>, 1: EloquentCollection<int, ProductVariant>}
     */
    private function loadCatalog(array $lines): array
    {
        /** @var EloquentCollection<int, Product> $products */
        $products = Product::query()
            // taxClass is eager-loaded because preventLazyLoading is on outside
            // production: resolving a rate per line would throw, not just be slow.
            ->with(['brand:id,name,slug', 'taxClass', 'media'])
            ->whereIn('id', array_map(fn (array $line): int => $line['product_id'], $lines))
            ->get()
            ->keyBy('id');

        $variantIds = array_values(array_filter(array_map(
            fn (array $line): ?int => $line['variant_id'],
            $lines,
        )));

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = $variantIds === []
            ? new EloquentCollection
            : ProductVariant::query()
                ->with(['media', 'attributeValues'])
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

        return [$products, $variants];
    }

    /** How many of this line may still be bought; null when stock is untracked. */
    private function availableQuantity(Product $product, ?ProductVariant $variant): ?int
    {
        $allowsBackorder = $variant !== null
            ? (bool) $variant->allow_backorder
            : (bool) $product->allow_backorder;

        if ($allowsBackorder) {
            return null;
        }

        $quantity = $variant !== null ? $variant->stock_quantity : $product->stock_quantity;

        return $quantity === null ? null : max(0, (int) $quantity);
    }

    // ==================================================
    // DISCOUNT
    // ==================================================

    /**
     * Split a discount across lines in proportion to their value.
     *
     * Integer division leaves a few cents over, so the remainder is handed out
     * one cent at a time to the lines that lost the most in the rounding —
     * largest remainder first. The shares therefore add up to exactly the
     * discount, which is what lets each line carry an honest `discount_cents`
     * and be taxed on it.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($amount <= 0 || $total <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $exact = $amount * $weight / $total;
            $shares[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $shares[$index];
        }

        $leftover = $amount - array_sum($shares);

        arsort($remainders);

        foreach (array_keys($remainders) as $index) {
            if ($leftover <= 0) {
                break;
            }

            $shares[$index]++;
            $leftover--;
        }

        ksort($shares);

        return array_values($shares);
    }

    // ==================================================
    // SHIPPING
    // ==================================================

    /** Pickup is free; delivery is the flat rate until the order earns its way out of it. */
    private function shippingCents(DeliveryMethod $delivery, int $discountedSubtotal): int
    {
        if ($delivery === DeliveryMethod::Pickup) {
            return 0;
        }

        return $discountedSubtotal >= $this->shippingSettings->free_shipping_threshold_cents
            ? 0
            : $this->shippingSettings->flat_rate_cents;
    }

    /** How much more is needed for free delivery, or null when it does not apply. */
    private function freeShippingRemaining(DeliveryMethod $delivery, int $discountedSubtotal): ?int
    {
        if ($delivery === DeliveryMethod::Pickup) {
            return null;
        }

        $remaining = $this->shippingSettings->free_shipping_threshold_cents - $discountedSubtotal;

        return $remaining > 0 ? $remaining : null;
    }

    // ==================================================
    // TAX
    // ==================================================

    private function pricesIncludeTax(): bool
    {
        return $this->taxSettings->prices_include_tax;
    }

    /**
     * Tax on one amount, at one rate.
     *
     * When prices include tax the figure is extracted from the amount, because
     * it is already inside it. When they do not, it is added on top. Rounded
     * here, per line, rather than once on the total — a line carries its own
     * `tax_cents` onto the order, so it has to be a whole number of cents in its
     * own right.
     */
    private function taxFor(int $amountCents, float $ratePercent): int
    {
        if ($ratePercent <= 0.0 || $amountCents <= 0) {
            return 0;
        }

        $rate = $ratePercent / 100;

        return $this->pricesIncludeTax()
            ? (int) round($amountCents - ($amountCents / (1 + $rate)))
            : (int) round($amountCents * $rate);
    }

    /**
     * The rate for a product: its own class, else the store default, else zero.
     *
     * Both the store's master switch and the product's own `is_taxable` flag
     * short-circuit to zero, so exempt goods stay exempt however the default is
     * configured.
     */
    private function rateForProduct(Product $product): float
    {
        if (! $this->taxSettings->tax_enabled || ! $product->is_taxable) {
            return 0.0;
        }

        // `??` already swallows a read on null, so the nullsafe operator would
        // be redundant here.
        return (float) ($product->taxClass->rate ?? $this->defaultTaxClass()->rate ?? 0);
    }

    /** The store default rate, used for delivery and as the per-product fallback. */
    private function defaultRate(): float
    {
        return $this->taxSettings->tax_enabled
            ? (float) ($this->defaultTaxClass()->rate ?? 0)
            : 0.0;
    }

    private function defaultTaxClass(): ?TaxClass
    {
        if ($this->defaultTaxClassResolved) {
            return $this->defaultTaxClass;
        }

        $this->defaultTaxClassResolved = true;

        $id = $this->taxSettings->default_tax_class_id;

        $this->defaultTaxClass = $id === null ? null : TaxClass::query()->find($id);

        return $this->defaultTaxClass;
    }

    // ==================================================
    // EMPTY
    // ==================================================

    private function emptyQuote(DeliveryMethod $delivery): CheckoutQuoteData
    {
        $minimum = $this->checkoutSettings->min_order_value_cents;

        return new CheckoutQuoteData(
            lines: [],
            totals: new OrderTotalsData(
                subtotalCents: 0,
                subtotalFormatted: money(0),
                discountCents: 0,
                discountFormatted: money(0),
                shippingCents: 0,
                shippingFormatted: money(0),
                taxCents: 0,
                taxFormatted: money(0),
                totalCents: 0,
                totalFormatted: money(0),
                pricesIncludeTax: $this->pricesIncludeTax(),
                taxLabel: OrderTotalsData::label($this->pricesIncludeTax()),
                couponCode: null,
                deliveryMethod: $delivery,
                shippingIsFree: false,
            ),
            minOrderValueCents: $minimum,
            minOrderValueFormatted: money($minimum),
            meetsMinimum: false,
            freeShippingRemainingCents: null,
            freeShippingRemainingFormatted: null,
            blockers: [],
        );
    }
}

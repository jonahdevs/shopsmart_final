<?php

namespace App\Data;

use App\Enums\DeliveryMethod;
use App\Models\Order;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The money panel: subtotal, discount, delivery, tax, total.
 *
 * This object exists so the arithmetic happens once, on the server. The
 * checkout page and the placed-order page render the same component from the
 * same shape — one built by the pricer, one read back off the order row — so
 * the two can never disagree about what a number means, and no client ever adds
 * up a total of its own.
 *
 * `taxCents` is informational when `pricesIncludeTax` is true: the tax is
 * already inside the line prices, so adding it again would double-charge.
 * `taxLabel` says which of the two is being shown.
 */
#[TypeScript]
class OrderTotalsData extends Data
{
    public function __construct(
        public int $subtotalCents,
        public string $subtotalFormatted,
        public int $discountCents,
        public string $discountFormatted,
        public int $shippingCents,
        public string $shippingFormatted,
        public int $taxCents,
        public string $taxFormatted,
        public int $totalCents,
        public string $totalFormatted,
        public bool $pricesIncludeTax,
        /** "VAT (incl.)" or "VAT", already localised. */
        public string $taxLabel,
        public ?string $couponCode,
        public DeliveryMethod $deliveryMethod,
        /** True when delivery was waived by the free-shipping threshold. */
        public bool $shippingIsFree,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return new self(
            subtotalCents: $order->subtotal_cents,
            subtotalFormatted: money($order->subtotal_cents),
            discountCents: $order->discount_cents,
            discountFormatted: money($order->discount_cents),
            shippingCents: $order->shipping_cents,
            shippingFormatted: money($order->shipping_cents),
            taxCents: $order->tax_cents,
            taxFormatted: money($order->tax_cents),
            totalCents: $order->total_cents,
            totalFormatted: money($order->total_cents),
            pricesIncludeTax: $order->prices_include_tax,
            taxLabel: self::label($order->prices_include_tax),
            couponCode: $order->coupon_code,
            deliveryMethod: $order->delivery_method,
            shippingIsFree: $order->shipping_cents === 0
                && $order->delivery_method === DeliveryMethod::Delivery,
        );
    }

    public static function label(bool $pricesIncludeTax): string
    {
        return $pricesIncludeTax ? __('VAT (incl.)') : __('VAT');
    }
}

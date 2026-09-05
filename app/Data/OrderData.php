<?php

namespace App\Data;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A placed order, as the customer sees it.
 *
 * Built entirely from the order's own columns and its frozen lines — never from
 * the catalog — so what a shopper reads here is what they bought, whatever has
 * happened to the products since.
 *
 * The totals are the same {@see OrderTotalsData} the checkout page rendered, so
 * both pages use one component and cannot drift apart.
 */
#[TypeScript]
class OrderData extends Data
{
    /**
     * @param  list<PricedLineData>  $lines
     */
    public function __construct(
        public int $id,
        public string $orderNumber,
        public OrderStatus $status,
        public string $statusLabel,
        public string $statusVariant,
        public PaymentStatus $paymentStatus,
        public string $paymentStatusLabel,
        public string $paymentStatusVariant,
        public ?string $paymentMethod,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public array $lines,
        public int $itemCount,
        public OrderTotalsData $totals,
        public ?AddressData $shippingAddress,
        public ?string $customerNote,
        public string $placedAt,
        public ?string $paidAt,
        /** True while the order can still be taken to a gateway. */
        public bool $awaitsPayment,
    ) {}

    public static function fromModel(Order $order): self
    {
        $order->loadMissing('items');

        $lines = array_values($order->items
            ->map(fn ($item): PricedLineData => PricedLineData::fromOrderItem($item))
            ->all());

        return new self(
            id: $order->getKey(),
            orderNumber: $order->order_number,
            status: $order->status,
            statusLabel: $order->status->label(),
            statusVariant: $order->status->badgeVariant(),
            paymentStatus: $order->payment_status,
            paymentStatusLabel: $order->payment_status->label(),
            paymentStatusVariant: $order->payment_status->badgeVariant(),
            paymentMethod: $order->payment_method,
            customerName: $order->customer_name,
            customerEmail: $order->customer_email,
            customerPhone: $order->customer_phone,
            lines: $lines,
            itemCount: (int) $order->items->sum('quantity'),
            totals: OrderTotalsData::fromOrder($order),
            shippingAddress: AddressData::fromOrder($order),
            customerNote: $order->customer_note,
            placedAt: $order->placed_at->toIso8601String(),
            paidAt: $order->paid_at?->toIso8601String(),
            awaitsPayment: $order->awaitsPayment(),
        );
    }
}

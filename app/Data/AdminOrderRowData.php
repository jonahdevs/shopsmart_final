<?php

namespace App\Data;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin orders table.
 *
 * Deliberately not {@see OrderData}: that object loads and maps every line of
 * the order, which is exactly right for a page showing one order and exactly
 * wrong for a page showing fifty. This carries only what the table renders, and
 * `itemCount` arrives as a `withSum` aggregate rather than a loaded relation.
 */
#[TypeScript]
class AdminOrderRowData extends Data
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $customerName,
        public string $customerEmail,
        public OrderStatus $status,
        public string $statusLabel,
        public string $statusVariant,
        public PaymentStatus $paymentStatus,
        public string $paymentStatusLabel,
        public string $paymentStatusVariant,
        public ?string $paymentMethod,
        public int $totalCents,
        public string $totalFormatted,
        public int $itemCount,
        public string $placedAt,
    ) {}

    public static function fromModel(Order $order): self
    {
        return new self(
            id: $order->getKey(),
            orderNumber: $order->order_number,
            customerName: $order->customer_name,
            customerEmail: $order->customer_email,
            status: $order->status,
            statusLabel: $order->status->label(),
            statusVariant: $order->status->badgeVariant(),
            paymentStatus: $order->payment_status,
            paymentStatusLabel: $order->payment_status->label(),
            paymentStatusVariant: $order->payment_status->badgeVariant(),
            paymentMethod: $order->payment_method,
            totalCents: $order->total_cents,
            totalFormatted: money($order->total_cents),
            // Set by `withSum('items', 'quantity')` in the query; absent when a
            // caller hands over a plain model, which the table never does.
            itemCount: (int) ($order->getAttribute('items_sum_quantity') ?? 0),
            placedAt: $order->placed_at->toIso8601String(),
        );
    }
}

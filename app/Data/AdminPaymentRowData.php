<?php

namespace App\Data;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One collection attempt, as staff read it.
 *
 * `payload` is never exposed. It is the gateway's raw response — encrypted at
 * rest precisely because it carries the payer's name, phone and masked
 * instrument — and none of that belongs in a JSON prop to render a table row.
 * `failureReason` is the one part of it staff actually need.
 */
#[TypeScript]
class AdminPaymentRowData extends Data
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $gateway,
        public ?string $channel,
        public PaymentStatus $status,
        public string $statusLabel,
        public string $statusVariant,
        public int $amountCents,
        public string $amountFormatted,
        public string $currency,
        public ?string $gatewayReference,
        public ?string $failureReason,
        public ?string $orderNumber,
        public ?string $paidAt,
        public string $createdAt,
    ) {}

    public static function fromModel(Payment $payment): self
    {
        return new self(
            id: $payment->getKey(),
            reference: $payment->reference,
            gateway: $payment->gateway,
            channel: $payment->channel,
            status: $payment->status,
            statusLabel: $payment->status->label(),
            statusVariant: $payment->status->badgeVariant(),
            amountCents: $payment->amount_cents,
            amountFormatted: money($payment->amount_cents),
            currency: $payment->currency,
            gatewayReference: $payment->gateway_reference,
            failureReason: $payment->failure_reason,
            orderNumber: $payment->order?->order_number,
            paidAt: $payment->paid_at?->toIso8601String(),
            createdAt: $payment->created_at?->toIso8601String() ?? '',
        );
    }
}

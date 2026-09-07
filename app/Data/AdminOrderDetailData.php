<?php

namespace App\Data;

use App\Enums\OrderStatus;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One order as staff read it: the customer's view plus what only staff may see.
 *
 * `order` is the very same {@see OrderData} the shopper's own page renders, so
 * the lines and the money panel are built once and cannot drift between the two
 * audiences. Everything alongside it is staff-only — the internal note, the
 * collection attempts, and the transitions this order can still make.
 */
#[TypeScript]
class AdminOrderDetailData extends Data
{
    /**
     * @param  list<AdminPaymentRowData>  $payments
     * @param  list<array{value: string, label: string}>  $availableStatuses
     */
    public function __construct(
        public OrderData $order,
        public ?string $staffNote,
        public array $payments,
        public array $availableStatuses,
        /** Null once the customer has deleted their account. */
        public ?int $customerId,
        public ?string $cancelledAt,
        /** True once the lines have been taken out of stock. */
        public bool $stockDeducted,
    ) {}

    public static function fromModel(Order $order): self
    {
        $order->loadMissing(['items', 'payments']);

        $payments = array_values($order->payments
            ->sortByDesc('created_at')
            ->map(fn ($payment): AdminPaymentRowData => AdminPaymentRowData::fromModel($payment))
            ->all());

        return new self(
            order: OrderData::fromModel($order),
            staffNote: $order->staff_note,
            payments: $payments,
            availableStatuses: self::transitionsFrom($order->status),
            customerId: $order->user_id,
            cancelledAt: $order->cancelled_at?->toIso8601String(),
            stockDeducted: $order->stock_deducted_at !== null,
        );
    }

    /**
     * The statuses this order may still be moved to.
     *
     * The lifecycle itself lives on {@see OrderStatus::allowedTransitions()},
     * which {@see UpdateOrderStatusRequest} also reads,
     * so the picker can never offer a move the form request would then refuse —
     * and the client never has to know the rules, only render them.
     *
     * The current status is not in this list: staying put is not a transition.
     * The page adds it to the select itself, as the selected option, so that
     * submitting the form untouched is the no-op it looks like.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function transitionsFrom(OrderStatus $current): array
    {
        return array_map(
            static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $current->allowedTransitions(),
        );
    }
}

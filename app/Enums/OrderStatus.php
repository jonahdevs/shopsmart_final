<?php

namespace App\Enums;

/**
 * Fulfilment lifecycle state of an order.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Processing'),
            self::OutForDelivery => __('Out for delivery'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::Refunded => __('Refunded'),
        };
    }

    /**
     * The shadcn-vue Badge variant used to render this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending, self::Refunded => 'outline',
            self::Processing, self::OutForDelivery => 'secondary',
            self::Completed => 'default',
            self::Cancelled => 'destructive',
        };
    }

    /**
     * Whether the order has reached a state that can no longer progress.
     *
     * Read off {@see allowedTransitions()} rather than listed again here, so the
     * two cannot disagree. Note that `completed` is NOT final: a delivered order
     * can still be refunded, which is the whole reason a refund exists.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * The statuses this one may legally be moved to.
     *
     * The lifecycle a Kenyan retail order actually runs: it is taken
     * (`pending`), picked and packed (`processing`), handed to a rider
     * (`out_for_delivery`) and signed for (`completed`). It can be called off at
     * any point before it is signed for, and only a completed order can be
     * refunded — money that was never collected cannot be given back. Cancelled
     * and refunded are both the end of the road.
     *
     * The current status is deliberately NOT in its own list: staying put is not
     * a move. {@see canTransitionTo()} answers true for it separately, because
     * re-saving the status a form was rendered with must be a no-op rather than
     * an error.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Cancelled],
            self::Processing => [self::OutForDelivery, self::Completed, self::Cancelled],
            self::OutForDelivery => [self::Completed, self::Cancelled],
            self::Completed => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    /**
     * Whether an order sitting in this status may be moved to `$status`.
     *
     * Staying on the same status is permitted — see {@see allowedTransitions()}
     * for why.
     */
    public function canTransitionTo(self $status): bool
    {
        return $status === $this
            || in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

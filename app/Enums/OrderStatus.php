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
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Refunded], true);
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

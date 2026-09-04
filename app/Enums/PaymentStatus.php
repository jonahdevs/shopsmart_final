<?php

namespace App\Enums;

/**
 * Settlement state of a payment attached to an order.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Success => __('Paid'),
            self::Failed => __('Failed'),
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
            self::Success => 'default',
            self::Failed => 'destructive',
            self::Cancelled => 'secondary',
        };
    }

    /**
     * Whether the payment has settled into a state that no longer changes on its own.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
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

<?php

namespace App\Enums;

/**
 * How an order reaches the customer.
 *
 * Pickup is only offered when ShippingSettings::$local_pickup_enabled is on,
 * and is always free; delivery is charged at the flat rate unless the order has
 * cleared the free-shipping threshold.
 */
enum DeliveryMethod: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => __('Delivery'),
            self::Pickup => __('Collect in store'),
        };
    }

    /** Whether this method needs a shipping address on the order. */
    public function requiresAddress(): bool
    {
        return $this === self::Delivery;
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

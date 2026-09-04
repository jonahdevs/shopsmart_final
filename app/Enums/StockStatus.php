<?php

namespace App\Enums;

/**
 * Availability of a product or variant for purchase.
 */
enum StockStatus: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Backorder = 'backorder';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('In Stock'),
            self::OutOfStock => __('Out of Stock'),
            self::Backorder => __('Backorder'),
        };
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

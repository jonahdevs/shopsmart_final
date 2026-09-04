<?php

namespace App\Enums;

/**
 * The relationship a linked product has to its parent product.
 */
enum ProductLinkType: string
{
    case Upsell = 'upsell';
    case CrossSell = 'cross_sell';
    case Accessory = 'accessory';
    case SparePart = 'spare_part';

    public function label(): string
    {
        return match ($this) {
            self::Upsell => __('Upsells'),
            self::CrossSell => __('Cross-sells'),
            self::Accessory => __('Accessories'),
            self::SparePart => __('Spare parts'),
        };
    }

    /**
     * The lucide icon name used to represent this link type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Upsell => 'trending-up',
            self::CrossSell => 'shopping-cart',
            self::Accessory => 'puzzle',
            self::SparePart => 'wrench',
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

<?php

namespace App\Enums;

/**
 * The kind of product record, used as the type discriminator for catalog items.
 */
enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Grouped = 'grouped';
    case Bundled = 'bundled';

    public function label(): string
    {
        return match ($this) {
            self::Simple => __('Simple'),
            self::Variable => __('Variable'),
            self::Grouped => __('Grouped'),
            self::Bundled => __('Bundled'),
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

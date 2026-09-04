<?php

namespace App\Enums;

/**
 * How a product attribute's values are presented to the shopper.
 */
enum AttributeType: string
{
    case Select = 'select';
    case Color = 'color';
    case Button = 'button';

    public function label(): string
    {
        return match ($this) {
            self::Select => __('Dropdown'),
            self::Color => __('Color swatch'),
            self::Button => __('Button'),
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

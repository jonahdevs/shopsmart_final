<?php

namespace App\Enums;

/**
 * Where a product may surface across the storefront catalog and search.
 */
enum ProductVisibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case Catalog = 'catalog';
    case Search = 'search';

    public function label(): string
    {
        return match ($this) {
            self::Visible => __('Visible'),
            self::Hidden => __('Hidden'),
            self::Catalog => __('Catalog Only'),
            self::Search => __('Search Only'),
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

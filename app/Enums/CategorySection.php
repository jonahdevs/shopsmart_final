<?php

namespace App\Enums;

/**
 * Storefront placements a category can be surfaced in.
 */
enum CategorySection: string
{
    case Navbar = 'navbar';
    case HomePageFeatured = 'homepage_featured';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Navbar => __('Navbar'),
            self::HomePageFeatured => __('Home Page Featured'),
            self::Footer => __('Footer'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Navbar => __('Categories shown in the main navigation menu.'),
            self::HomePageFeatured => __('Categories displayed in the "Shop by category" grid on the homepage.'),
            self::Footer => __('Category links in the site footer.'),
        };
    }

    /**
     * The lucide icon name used to represent this placement.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Navbar => 'menu',
            self::HomePageFeatured => 'house',
            self::Footer => 'file-text',
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

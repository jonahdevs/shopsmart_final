<?php

namespace App\Enums;

/**
 * A class of storage a visitor is asked about before anything belonging to it
 * is allowed to load.
 *
 * `Necessary` covers the session, CSRF and cart cookies the store cannot work
 * without: it is never asked about and can never be declined, so it carries no
 * consent of its own. Every other category is optional and starts denied — a
 * third-party tag whose category has not been granted is never rendered into
 * the page at all, so no request to the vendor is made and no cookie of theirs
 * is written.
 */
enum ConsentCategory: string
{
    case Necessary = 'necessary';
    case Analytics = 'analytics';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => __('Strictly necessary'),
            self::Analytics => __('Analytics'),
            self::Marketing => __('Marketing'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Necessary => __('Signing in, your basket and keeping the checkout secure. These cannot be switched off.'),
            self::Analytics => __('Counts visits and which pages are used, so we can see what is worth improving.'),
            self::Marketing => __('Measures whether an advert led to a purchase and lets us show you relevant ones.'),
        };
    }

    /**
     * Whether the visitor gets a say. Only optional categories are ever stored
     * in the consent cookie or offered on the banner.
     */
    public function isOptional(): bool
    {
        return $this !== self::Necessary;
    }

    /**
     * @return list<self>
     */
    public static function optional(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $category): bool => $category->isOptional(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function optionalValues(): array
    {
        return array_map(
            static fn (self $category): string => $category->value,
            self::optional(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function optionalOptions(): array
    {
        return array_map(
            static fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            self::optional(),
        );
    }
}

<?php

namespace App\Enums;

/**
 * Lifecycle state of a catalog category.
 */
enum CategoryStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Archived => __('Archived'),
        };
    }

    /**
     * The shadcn-vue Badge variant used to render this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Active => 'default',
            self::Inactive => 'outline',
            self::Archived => 'destructive',
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

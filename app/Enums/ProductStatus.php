<?php

namespace App\Enums;

/**
 * Publication state of a product within the catalog workflow.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Scheduled => __('Scheduled'),
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
            self::Published => 'default',
            self::Scheduled => 'outline',
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

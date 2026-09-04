<?php

namespace App\Enums;

/**
 * Moderation state of a customer product review.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }

    /**
     * The shadcn-vue Badge variant used to render this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'outline',
            self::Approved => 'default',
            self::Rejected => 'destructive',
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

<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How a review is attributed on the storefront.
 *
 * `reviews.author_name` is a snapshot taken when the review was written and it
 * deliberately survives the reviewer deleting their account, so it is the one
 * piece of a departed customer's identity the store keeps publishing. This
 * setting decides how much of it is shown.
 */
enum ReviewAuthorFormat: string
{
    case FullName = 'full_name';
    case FirstNameAndInitial = 'first_name_initial';

    public function label(): string
    {
        return match ($this) {
            self::FullName => __('Full name (Jane Wanjiru)'),
            self::FirstNameAndInitial => __('First name and initial (Jane W.)'),
        };
    }

    /**
     * Render a snapshotted author name for public display.
     *
     * A one-word name is returned unchanged — there is no surname to shorten,
     * and appending a stray initial would invent one.
     */
    public function apply(string $authorName): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $authorName) ?? '');

        if ($this === self::FullName || $name === '') {
            return $name;
        }

        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            return $name;
        }

        $surname = (string) array_pop($parts);
        $initial = Str::upper(Str::substr($surname, 0, 1));

        return $parts[0].' '.$initial.'.';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

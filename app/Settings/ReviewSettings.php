<?php

namespace App\Settings;

use App\Enums\ReviewAuthorFormat;
use Spatie\LaravelSettings\Settings;

/**
 * Product review moderation: whether reviews are collected, who may leave one,
 * whether they publish without a human check, and how much of the reviewer's
 * name the storefront prints.
 */
class ReviewSettings extends Settings
{
    public bool $reviews_enabled;

    public bool $require_verified_purchase;

    public bool $auto_approve;

    /**
     * A {@see ReviewAuthorFormat} value. Held as a string because the settings
     * repository stores scalars; read it through {@see authorFormat()}.
     */
    public string $author_display_format;

    public static function group(): string
    {
        return 'reviews';
    }

    /**
     * Falls back to the privacy-preserving format rather than the permissive
     * one if the stored value is ever unrecognisable.
     */
    public function authorFormat(): ReviewAuthorFormat
    {
        return ReviewAuthorFormat::tryFrom($this->author_display_format)
            ?? ReviewAuthorFormat::FirstNameAndInitial;
    }
}

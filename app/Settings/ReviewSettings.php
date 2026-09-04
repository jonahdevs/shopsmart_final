<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Product review moderation: whether reviews are collected, who may leave one,
 * and whether they publish without a human check.
 */
class ReviewSettings extends Settings
{
    public bool $reviews_enabled;

    public bool $require_verified_purchase;

    public bool $auto_approve;

    public static function group(): string
    {
        return 'reviews';
    }
}
